<?php

namespace App\Http\Controllers;

use App\Models\ScannedDocument;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ScannerController extends Controller
{
    /**
     * Display scanner form.
     */
    public function index()
    {
        return view('scanner');
    }


    /**
     * Scan document using scanner service.
     */
    public function scan()
    {
        try {

            $response = Http::timeout(120)
                ->acceptJson()
                ->post('http://localhost:8080/api/v1/scan', [
                    'params' => [
                        'deviceId' => 'airscan:e0:HP M281fdw',
                        'resolution' => 200,
                        'mode' => 'Color',
                        'source' => 'Flatbed',
                        'adfMode' => 'Simplex',
                        'top' => 0,
                        'left' => 0,
                        'width' => 210,
                        'height' => 297,
                    ],

                    'pipeline' => 'JPG | @:pipeline.high-quality',
                    'batch' => 'none',
                    'index' => 0,
                ]);

            if (!$response->successful()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Scanner service returned an error.',
                    'scanner_response' => $response->body(),
                ], 500);
            }

            $data = $response->json();

            if (!isset($data['file']['fullname'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Scanner did not return a file.',
                    'scanner_response' => $data,
                ], 500);
            }

            $scannerFile = $data['file']['fullname'];

            /*
             * Scanner returned:
             *
             * data/output/scan_2026-08-12 13.37.57.jpg
             *
             * We need to locate this file on the machine.
             */

            $scannerBasePath = base_path('../');

            $fullScannerPath = $scannerBasePath . DIRECTORY_SEPARATOR . $scannerFile;

            /*
             * If your scanner service runs from another directory,
             * we can change this path later.
             */

            if (!file_exists($fullScannerPath)) {

                return response()->json([
                    'success' => false,
                    'message' => 'Scanner created the file, but Laravel cannot find it.',
                    'scanner_file' => $scannerFile,
                    'looked_for' => $fullScannerPath,
                ], 500);
            }

            /*
             * Generate a safe filename for Laravel storage.
             */

            $extension = pathinfo(
                $scannerFile,
                PATHINFO_EXTENSION
            ) ?: 'jpg';

            $filename = 'scan_' .
                now()->format('Ymd_His') .
                '_' .
                Str::random(8) .
                '.' .
                $extension;

            $storagePath = 'scans/' . $filename;

            /*
             * Copy scanner file into Laravel storage.
             */

            Storage::disk('public')->put(
                $storagePath,
                file_get_contents($fullScannerPath)
            );

            /*
             * Generate browser URL.
             */

            $url = Storage::disk('public')->url($storagePath);

            return response()->json([
                'success' => true,

                'message' => 'Document scanned successfully.',

                'file' => [
                    'name' => $filename,
                    'path' => $storagePath,
                    'url' => $url,
                    'mime_type' => mime_content_type($fullScannerPath),
                    'size' => filesize($fullScannerPath),
                ],
            ]);

        } catch (\Throwable $e) {

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }


    /**
     * Save scanned document to database.
     */
    public function store(Request $request)
    {
        $request->validate([
            'file_path' => ['required', 'string'],
            'file_url' => ['required', 'string'],
            'original_name' => ['required', 'string'],
            'mime_type' => ['nullable', 'string'],
            'file_size' => ['nullable', 'integer'],
        ]);

        $document = ScannedDocument::create([
            'original_name' => $request->original_name,
            'file_path' => $request->file_path,
            'file_url' => $request->file_url,
            'mime_type' => $request->mime_type,
            'file_size' => $request->file_size,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Document saved successfully.',
            'document' => $document,
        ]);
    }
}