<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <meta
        name="csrf-token"
        content="{{ csrf_token() }}"
    >

    <title>Document Scanner</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 30px;
            font-family: Arial, sans-serif;
            background: #f5f7fa;
        }

        .container {
            max-width: 900px;
            margin: auto;
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,.08);
        }

        h1 {
            margin-top: 0;
        }

        .buttons {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            margin-bottom: 20px;
        }

        button {
            padding: 12px 20px;
            border: 0;
            border-radius: 6px;
            cursor: pointer;
            font-size: 15px;
        }

        #singleScanBtn {
            background: #2563eb;
            color: white;
        }

        #adfScanBtn {
            background: #7c3aed;
            color: white;
        }

        #saveBtn {
            background: #16a34a;
            color: white;
        }

        button:disabled {
            opacity: .5;
            cursor: not-allowed;
        }

        .status {
            padding: 15px;
            margin: 15px 0;
            background: #f1f5f9;
            border-radius: 6px;
            white-space: pre-wrap;
            word-break: break-word;
        }

        .preview {
            margin-top: 20px;
            display: none;
        }

        .preview img {
            max-width: 100%;
            max-height: 700px;
            border: 1px solid #ddd;
        }

        .pdf-preview {
            width: 100%;
            height: 700px;
            border: 1px solid #ddd;
        }

        .info {
            margin-top: 10px;
            color: #555;
        }
    </style>
</head>

<body>

<div class="container">

    <h1>Document Scanner</h1>

    <p>
        Choose how you want to scan your document.
    </p>

    <div class="buttons">

        <button
            type="button"
            id="singleScanBtn"
            onclick="scanSingle()"
        >
            Scan Single Page
        </button>

        <button
            type="button"
            id="adfScanBtn"
            onclick="scanADF()"
        >
            Scan ADF
        </button>

        <button
            type="button"
            id="saveBtn"
            onclick="saveDocument()"
            disabled
        >
            Save Document
        </button>

        <button type="button" id="clearBtn" class="btn btn-secondary">
            Clear
        </button>

    </div>

    <div
        id="status"
        class="status"
    >
        Ready.
    </div>

    <div
        id="preview"
        class="preview"
    >

        <h3>Scanned Document</h3>

        <img
            id="imagePreview"
            src=""
            alt="Scanned document"
        >

        <iframe
            id="pdfPreview"
            class="pdf-preview"
            src=""
            style="display:none;"
        ></iframe>

        <div
            id="fileInfo"
            class="info"
        ></div>

    </div>

</div>

<script>
document.getElementById('clearBtn').addEventListener('click', function () {

    // Clear status
    const status = document.getElementById('status');
    if (status) {
        status.innerHTML = '';
        status.textContent = '';
    }

    // Clear scanned document preview
    const preview = document.getElementById('preview');
    if (preview) {
        preview.innerHTML = '';
        preview.style.display = 'none';
    }

    // Clear scanned file information
    const scannedDocument = document.getElementById('scannedDocument');
    if (scannedDocument) {
        scannedDocument.innerHTML = '';
    }

    // Clear hidden inputs
    document.querySelectorAll(
        'input[name="filename"], input[name="temp_path"], input[name="scan_type"]'
    ).forEach(input => {
        input.value = '';
    });

});

    let scannedFile = null;

    const csrfToken =
        document
            .querySelector('meta[name="csrf-token"]')
            .getAttribute('content');

    const statusBox =
        document.getElementById('status');

    const saveBtn =
        document.getElementById('saveBtn');

    const singleScanBtn =
        document.getElementById('singleScanBtn');

    const adfScanBtn =
        document.getElementById('adfScanBtn');

    const preview =
        document.getElementById('preview');

    const imagePreview =
        document.getElementById('imagePreview');

    const pdfPreview =
        document.getElementById('pdfPreview');

    const fileInfo =
        document.getElementById('fileInfo');


    function setStatus(message)
    {
        statusBox.textContent = message;
    }


    function setScanning(scanning)
    {
        singleScanBtn.disabled = scanning;
        adfScanBtn.disabled = scanning;

        if (scanning) {
            saveBtn.disabled = true;
        }
    }


    function showPreview(file)
    {
        preview.style.display = 'block';

        imagePreview.style.display = 'none';
        pdfPreview.style.display = 'none';

        const extension =
            file.filename
                .split('.')
                .pop()
                .toLowerCase();

        if (extension === 'pdf') {

            pdfPreview.src =
                file.url;

            pdfPreview.style.display =
                'block';

        } else {

            imagePreview.src =
                file.url;

            imagePreview.style.display =
                'block';
        }

        fileInfo.textContent =
            'File: ' + file.filename;
    }


    /*
     * -------------------------------------------------------
     * SINGLE PAGE
     * -------------------------------------------------------
     */

    async function scanSingle()
    {
        setScanning(true);

        setStatus(
            'Scanning single page...\nPlease wait.'
        );

        preview.style.display = 'none';

        try {

            const response =
                await fetch(
                    "{{ route('scanner.scan') }}",
                    {
                        method: 'POST',

                        headers: {
                            'Content-Type':
                                'application/json',

                            'Accept':
                                'application/json',

                            'X-CSRF-TOKEN':
                                csrfToken
                        },

                        body: JSON.stringify({})
                    }
                );


            const text =
                await response.text();


            let data;

            try {
                data = JSON.parse(text);
            }
            catch (e) {

                throw new Error(
                    'Laravel returned an invalid response:\n\n' +
                    text.substring(0, 1000)
                );
            }


            if (!response.ok || !data.success) {

                throw new Error(
                    data.message ||
                    data.response ||
                    'Single page scan failed.'
                );
            }


            scannedFile = data;

            showPreview(data);

            saveBtn.disabled = false;

            setStatus(
                'Single page scan completed.\n' +
                'Click "Save Document" to save it.'
            );

        }
        catch (error) {

            scannedFile = null;

            saveBtn.disabled = true;

            setStatus(
                'ERROR:\n' +
                error.message
            );

        }
        finally {

            setScanning(false);
        }
    }


    /*
     * -------------------------------------------------------
     * ADF MULTI-PAGE
     * -------------------------------------------------------
     */

    async function scanADF()
    {
        setScanning(true);

        saveBtn.disabled = true;

        scannedFile = null;

        preview.style.display = 'none';

        setStatus(
            'ADF scanning started.\n\n' +
            'Make sure all pages are inside the document feeder.\n' +
            'ScanServJS will scan all pages and create ONE PDF.\n\n' +
            'Please wait...'
        );


        try {

            /*
             * THIS IS THE FETCH YOU ASKED ABOUT.
             *
             * It calls:
             *
             * POST /scan/adf
             *
             * which maps to:
             *
             * DocumentScannerController@scanAdf
             */

            const response =
                await fetch(
                    "{{ route('scanner.scanAdf') }}",
                    {
                        method: 'POST',

                        headers: {
                            'Content-Type':
                                'application/json',

                            'Accept':
                                'application/json',

                            'X-CSRF-TOKEN':
                                csrfToken
                        },

                        body: JSON.stringify({})
                    }
                );


            const text =
                await response.text();


            let data;

            try {

                data =
                    JSON.parse(text);

            }
            catch (e) {

                throw new Error(
                    'Laravel returned an invalid response:\n\n' +
                    text.substring(0, 2000)
                );
            }


            if (!response.ok || !data.success) {

                let message =
                    data.message ||
                    'ADF scan failed.';


                if (data.response) {

                    message +=
                        '\n\nScanServJS response:\n' +
                        data.response;
                }


                throw new Error(message);
            }


            /*
             * ScanServJS has already created
             * the complete multi-page PDF.
             */

            scannedFile = data;

            showPreview(data);

            saveBtn.disabled = false;


            setStatus(
                'ADF scan completed successfully.\n\n' +
                'All feeder pages have been combined into one PDF.\n\n' +
                'Click "Save Document" to store the PDF.'
            );

        }
        catch (error) {

            scannedFile = null;

            saveBtn.disabled = true;

            setStatus(
                'ERROR:\n\n' +
                error.message
            );

        }
        finally {

            setScanning(false);
        }
    }


    /*
     * -------------------------------------------------------
     * SAVE
     * -------------------------------------------------------
     */

    async function saveDocument()
    {
        if (!scannedFile) {

            setStatus(
                'Nothing to save. Scan a document first.'
            );

            return;
        }


        saveBtn.disabled = true;

        setStatus(
            'Saving document into Laravel storage and database...\nPlease wait.'
        );


        try {

            const response =
                await fetch(
                    "{{ route('scanner.save') }}",
                    {
                        method: 'POST',

                        headers: {
                            'Content-Type':
                                'application/json',

                            'Accept':
                                'application/json',

                            'X-CSRF-TOKEN':
                                csrfToken
                        },

                        body: JSON.stringify({

                            filename:
                                scannedFile.filename,

                            temp_path:
                                scannedFile.temp_path,

                            scan_type:
                                scannedFile.scan_type

                        })
                    }
                );


            const text =
                await response.text();


            let data;

            try {

                data =
                    JSON.parse(text);

            }
            catch (e) {

                throw new Error(
                    'Laravel returned an invalid response:\n\n' +
                    text.substring(0, 2000)
                );
            }


            if (!response.ok || !data.success) {

                throw new Error(
                    data.message ||
                    'Failed to save document.'
                );
            }


            setStatus(
                'SUCCESS!\n\n' +
                data.message +
                '\n\n' +
                'File: ' +
                data.document.file_name +
                '\n' +
                'Type: ' +
                data.document.type +
                '\n' +
                'Scan type: ' +
                data.document.scan_type +
                '\n' +
                'Storage: ' +
                data.document.path
            );


            /*
             * Document has now been permanently saved.
             */

            scannedFile = null;

            saveBtn.disabled = true;

        }
        catch (error) {

            saveBtn.disabled = false;

            setStatus(
                'ERROR:\n\n' +
                error.message
            );
        }
    }

</script>

</body>
</html>