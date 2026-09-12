<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class DocumentScanner extends Controller
{
    private const ALLOWED_MIME_TYPES = [
        'image/jpeg',
        'image/png',
        'application/pdf',
    ];

    private const MAX_FILE_SIZE_KILOBYTES = 20480;

    public function ScanDocument()
    {
        return view('scanner');
    }

    public function UploadScan(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'scan' => ['required', 'file', 'max:'.self::MAX_FILE_SIZE_KILOBYTES],
                'format' => ['required', 'string', 'in:jpeg,png,pdf'],
            ]);
        } catch (ValidationException $exception) {
            return response()->json([
                'success' => false,
                'message' => 'The uploaded file is invalid.',
                'errors' => $exception->errors(),
            ], 422);
        }

        $uploadedFile = $request->file('scan');
        $declaredMimeType = $uploadedFile->getMimeType();

        if (! in_array($declaredMimeType, self::ALLOWED_MIME_TYPES, true)) {
            return response()->json([
                'success' => false,
                'message' => 'Unsupported file type. Only JPEG, PNG, and PDF are allowed.',
            ], 415);
        }

        $extension = match ($validated['format']) {
            'jpeg' => 'jpg',
            'png' => 'png',
            'pdf' => 'pdf',
        };

        $fileName = 'scan_'.Str::uuid()->toString().'.'.$extension;
        $relativePath = 'scans/'.$fileName;

        Storage::disk('public')->putFileAs(
            'scans',
            $uploadedFile,
            $fileName,
        );

        return response()->json([
            'success' => true,
            'message' => 'Scan uploaded successfully.',
            'data' => [
                'path' => $relativePath,
                'url' => Storage::disk('public')->url($relativePath),
                'format' => $validated['format'],
                'size' => $uploadedFile->getSize(),
            ],
        ], 201);
    }
}
