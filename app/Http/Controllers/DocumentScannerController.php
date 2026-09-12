<?php

namespace App\Http\Controllers;

use App\Models\ScannedDocument;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DocumentScannerController extends Controller
{
    private string $scanServUrl = 'http://localhost:8080';

    private string $deviceId = 'airscan:e0:HP M281fdw';

    /**
     * Scanner page
     */
    public function index()
    {
        return view('scanner');
    }

    /**
     * ---------------------------------------------------------
     * SINGLE PAGE SCAN
     * ---------------------------------------------------------
     */
    public function scan()
    {
        try {
            $payload = [
                'params' => [
                    'deviceId' => $this->deviceId,
                    'top' => 0,
                    'left' => 0,
                    'width' => 210,
                    'height' => 297,
                    'pageWidth' => 210,
                    'pageHeight' => 297,
                    'resolution' => 300,
                    'mode' => 'Color',
                    'source' => 'Flatbed',
                    'adfMode' => 'Simplex',
                    'brightness' => 0,
                    'contrast' => 0,
                    'dynamicLineart' => false,
                    'ald' => 'yes',
                ],

                'pipeline' => 'JPG | @:pipeline.high-quality',

                'batch' => 'none',

                'index' => 0,
            ];

            $response = Http::timeout(180)
                ->acceptJson()
                ->post(
                    $this->scanServUrl . '/api/v1/scan',
                    $payload
                );

            if (!$response->successful()) {
                return response()->json([
                    'success' => false,
                    'message' => 'ScanServJS returned an error.',
                    'status' => $response->status(),
                    'response' => $response->body(),
                ], 500);
            }

            $result = $response->json();

            if (
                !isset($result['file']) ||
                empty($result['file']['name'])
            ) {
                return response()->json([
                    'success' => false,
                    'message' => 'ScanServJS did not return a scanned file.',
                    'response' => $result,
                ], 500);
            }

            $scannerFilename = $result['file']['name'];

            $fileResponse = Http::timeout(180)
                ->get(
                    $this->scanServUrl .
                    '/api/v1/files/' .
                    rawurlencode($scannerFilename)
                );

            if (!$fileResponse->successful()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Laravel could not download the scanned image.',
                    'status' => $fileResponse->status(),
                    'filename' => $scannerFilename,
                ], 500);
            }

            $filename =
                'scan_' .
                now()->format('Ymd_His') .
                '_' .
                Str::random(8) .
                '.jpg';

            $tempPath = 'scanner-temp/' . $filename;

            Storage::disk('public')->put(
                $tempPath,
                $fileResponse->body()
            );

            return response()->json([
                'success' => true,
                'message' => 'Single page scanned successfully.',

                'scan_type' => 'single',

                'filename' => $filename,

                'temp_path' => $tempPath,

                'url' => Storage::disk('public')->url($tempPath),

                'scanner_filename' => $scannerFilename,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * ---------------------------------------------------------
     * ADF MULTI-PAGE SCAN
     * ---------------------------------------------------------
     *
     * ScanServJS itself creates ONE multi-page PDF.
     */
    public function scanAdf(Request $request)
    {
        try {
            /*
             * This is the important configuration.
             *
             * It matches the working ScanServJS UI:
             *
             * Device:
             * airscan:e0:HP M281fdw
             *
             * Source:
             * ADF
             *
             * Resolution:
             * 300
             *
             * Mode:
             * Color
             *
             * Batch:
             * Auto (Document feeder)
             *
             * Pipeline:
             * PDF (JPG | Low quality)
             */

            $payload = [
                'params' => [
                    'deviceId' => $this->deviceId,

                    'top' => 0,
                    'left' => 0,

                    'width' => 215.9,
                    'height' => 297,

                    'pageWidth' => 215.9,
                    'pageHeight' => 297,

                    'resolution' => 300,

                    'mode' => 'Color',

                    /*
                     * IMPORTANT
                     */
                    'source' => 'ADF',

                    /*
                     * Simplex means one side of each sheet.
                     */
                    'adfMode' => 'Simplex',

                    'brightness' => 0,
                    'contrast' => 0,

                    'dynamicLineart' => false,
                    'ald' => 'yes',
                ],

                /*
                 * IMPORTANT
                 *
                 * Do NOT use:
                 *
                 * PDF | @:pipeline.high-quality
                 *
                 * That is invalid in your ScanServJS installation.
                 *
                 * Your ScanServJS supports:
                 *
                 * PDF (JPG | @:pipeline.low-quality)
                 */
                'pipeline' => 'PDF (JPG | @:pipeline.low-quality)',

                /*
                 * THIS IS THE IMPORTANT PART FOR ADF.
                 *
                 * "auto" tells ScanServJS to keep taking
                 * pages from the document feeder.
                 */
                'batch' => 'auto',

                'index' => 0,
            ];

            \Log::info('Starting ScanServJS ADF scan', [
                'payload' => $payload,
            ]);

            $response = Http::timeout(600)
                ->acceptJson()
                ->post(
                    $this->scanServUrl . '/api/v1/scan',
                    $payload
                );

            \Log::info('ScanServJS ADF response', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            if (!$response->successful()) {
                return response()->json([
                    'success' => false,
                    'message' => 'ScanServJS returned an error.',
                    'status' => $response->status(),
                    'response' => $response->body(),
                    'payload' => $payload,
                ], 500);
            }

            $result = $response->json();

            if (
                !isset($result['file']) ||
                empty($result['file']['name'])
            ) {
                return response()->json([
                    'success' => false,
                    'message' => 'ScanServJS did not return the PDF.',
                    'response' => $result,
                ], 500);
            }

            $scannerFilename = $result['file']['name'];

            /*
             * Verify ScanServJS created a PDF.
             */
            $extension = strtolower(
                pathinfo($scannerFilename, PATHINFO_EXTENSION)
            );

            if ($extension !== 'pdf') {
                return response()->json([
                    'success' => false,
                    'message' => 'ScanServJS returned a file, but it is not a PDF.',
                    'file' => $result['file'],
                ], 500);
            }

            /*
             * Download the generated multi-page PDF.
             */
            $pdfResponse = Http::timeout(600)
                ->get(
                    $this->scanServUrl .
                    '/api/v1/files/' .
                    rawurlencode($scannerFilename)
                );

            if (!$pdfResponse->successful()) {
                return response()->json([
                    'success' => false,
                    'message' =>
                        'ScanServJS created the PDF, but Laravel could not download it.',

                    'status' => $pdfResponse->status(),

                    'scanner_filename' => $scannerFilename,

                    'response' => $pdfResponse->body(),
                ], 500);
            }

            $filename =
                'adf_' .
                now()->format('Ymd_His') .
                '_' .
                Str::random(8) .
                '.pdf';

            /*
             * Temporary location.
             */
            $tempPath =
                'scanner-temp/' .
                $filename;

            Storage::disk('public')->put(
                $tempPath,
                $pdfResponse->body()
            );

            return response()->json([
                'success' => true,

                'message' =>
                    'ADF scan completed. All feeder pages were combined into one PDF.',

                'scan_type' => 'adf',

                'filename' => $filename,

                'temp_path' => $tempPath,

                'url' =>
                    Storage::disk('public')->url($tempPath),

                'scanner_filename' => $scannerFilename,

                'size' =>
                    Storage::disk('public')->size($tempPath),
            ]);
        } catch (\Throwable $e) {
            \Log::error('ADF scanner error', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * ---------------------------------------------------------
     * SAVE DOCUMENT
     * ---------------------------------------------------------
     *
     * Works for BOTH:
     *
     * JPG single scan
     * PDF ADF scan
     */
    public function save(Request $request)
    {
        try {
            $request->validate([
                'filename' => 'required|string',
                'temp_path' => 'required|string',
                'scan_type' => 'required|in:single,adf',
            ]);

            $tempPath = $request->input('temp_path');

            /*
             * Security:
             * only allow our temporary scanner folder.
             */
            if (!Str::startsWith($tempPath, 'scanner-temp/')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid temporary file.',
                ], 422);
            }

            $disk = Storage::disk('public');

            if (!$disk->exists($tempPath)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Temporary scanned file was not found.',
                ], 404);
            }

            /*
             * Original filename from request.
             */
            $originalName = basename(
                $request->input('filename')
            );

            /*
             * Extension.
             */
            $extension = strtolower(
                pathinfo($originalName, PATHINFO_EXTENSION)
            );

            if (!$extension) {
                $extension =
                    $request->input('scan_type') === 'adf'
                        ? 'pdf'
                        : 'jpg';
            }

            /*
             * Final filename.
             */
            $fileName =
                'scan_' .
                now()->format('Ymd_His') .
                '_' .
                Str::random(8) .
                '.' .
                $extension;

            /*
             * Final storage location.
             */
            $finalPath =
                'documents/' .
                $fileName;

            $disk->makeDirectory('documents');

            /*
             * Copy temporary file to permanent storage.
             */
            $disk->copy(
                $tempPath,
                $finalPath
            );

            /*
             * Size.
             */
            $fileSize =
                $disk->size($finalPath);

            /*
             * URL.
             */
            $fileUrl =
                $disk->url($finalPath);

            /*
             * -------------------------------------------------
             * SAVE INTO YOUR EXISTING TABLE
             * -------------------------------------------------
             */
            $document = ScannedDocument::create([
                'original_name' => $originalName,

                'file_name' => $fileName,

                'file_path' => $finalPath,

                'file_url' => $fileUrl,

                'file_type' => $extension,

                'scan_type' =>
                    $request->input('scan_type'),

                'file_size' => $fileSize,
            ]);

            /*
             * Delete temporary file.
             */
            $disk->delete($tempPath);

            return response()->json([
                'success' => true,

                'message' =>
                    $request->input('scan_type') === 'adf'
                        ? 'ADF multi-page PDF saved successfully.'
                        : 'Scanned document saved successfully.',

                'document' => [
                    'id' => $document->id,

                    'original_name' =>
                        $document->original_name,

                    'file_name' =>
                        $document->file_name,

                    'path' =>
                        $document->file_path,

                    'url' =>
                        $document->file_url,

                    'type' =>
                        $document->file_type,

                    'scan_type' =>
                        $document->scan_type,

                    'size' =>
                        $document->file_size,
                ],
            ]);
        } catch (\Throwable $e) {
            \Log::error(
                'Document scanner save error',
                [
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]
            );

            return response()->json([
                'success' => false,

                'message' =>
                    'Failed to save document: ' .
                    $e->getMessage(),
            ], 500);
        }
    }
}