<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>@yield('title', 'Certificate')</title>
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: 'Georgia', 'Times New Roman', serif;
            color: #2f4a4a;
            background: #f5f2ea;
            margin: 0;
            padding: 32px 16px;
        }
        .toolbar {
            max-width: 820px;
            margin: 0 auto 16px auto;
            display: flex;
            justify-content: flex-end;
        }
        .print-btn {
            font-family: Arial, sans-serif;
            background: #8CA089;
            color: #fff;
            border: none;
            border-radius: 999px;
            padding: 10px 24px;
            font-size: 13px;
            font-weight: 600;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            cursor: pointer;
        }
        .print-btn:hover { background: #7c9078; }
        .certificate {
            position: relative;
            max-width: 820px;
            margin: 0 auto 28px auto;
            background: #fffdf8;
            border: 3px double #b7a970;
            border-radius: 4px;
            padding: 48px 56px;
            page-break-after: always;
            overflow: hidden;
        }
        .certificate:last-of-type { page-break-after: auto; }
        .cert-watermark {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 480px;
            max-width: 70%;
            opacity: 0.07;
            pointer-events: none;
            z-index: 0;
        }
        .cert-header {
            position: relative;
            z-index: 1;
            display: flex;
            align-items: center;
            gap: 14px;
            justify-content: center;
        }
        .cert-logo {
            width: 56px;
            height: 56px;
            object-fit: contain;
            flex-shrink: 0;
        }
        .cert-header-text { text-align: left; }
        .parish-name {
            text-align: left;
            text-transform: uppercase;
            letter-spacing: 0.2em;
            font-size: 12px;
            color: #8CA089;
            font-weight: 600;
            font-family: Arial, sans-serif;
            margin: 0;
        }
        .parish-address {
            text-align: left;
            font-family: Arial, sans-serif;
            font-size: 11px;
            color: #7a8a8a;
            margin: 4px 0 0 0;
        }
        .cert-body-wrap {
            position: relative;
            z-index: 1;
        }
        .cert-title {
            text-align: center;
            color: #3f6470;
            font-size: 30px;
            letter-spacing: 0.04em;
            margin: 24px 0 4px 0;
        }
        .cert-subtitle {
            text-align: center;
            font-family: Arial, sans-serif;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.15em;
            color: #b7a970;
            margin: 0 0 28px 0;
        }
        .cert-body {
            font-size: 16px;
            line-height: 2;
            text-align: center;
            margin: 0 auto;
            max-width: 620px;
        }
        .cert-body .name {
            display: inline-block;
            font-size: 22px;
            font-weight: 700;
            color: #3f6470;
            border-bottom: 1px solid #b7a970;
            padding: 0 6px 2px 6px;
            margin: 0 2px;
        }
        .cert-body .fill {
            display: inline-block;
            font-weight: 600;
            color: #2f4a4a;
            border-bottom: 1px solid #d8d2c2;
            padding: 0 4px;
            margin: 0 2px;
        }
        .divider {
            border: none;
            border-top: 1px solid #e4dfd2;
            margin: 32px 0;
        }
        table.fields { width: 100%; border-collapse: collapse; font-family: Arial, sans-serif; font-size: 13px; margin-top: 8px; }
        table.fields td { padding: 5px 0; vertical-align: top; }
        table.fields td.label { width: 190px; color: #7a8a8a; text-transform: uppercase; font-size: 10.5px; letter-spacing: 0.06em; }
        table.fields td.value { font-weight: 600; color: #2f4a4a; }
        .signature-row {
            margin-top: 56px;
            display: flex;
            justify-content: space-between;
            font-family: Arial, sans-serif;
            font-size: 11px;
            color: #7a8a8a;
        }
        .signature-line {
            width: 240px;
            border-top: 1px solid #b9c2c2;
            padding-top: 6px;
            text-align: center;
        }
        .footnote {
            margin-top: 24px;
            font-family: Arial, sans-serif;
            font-size: 10.5px;
            color: #a3a3a3;
            text-align: center;
        }
        @media print {
            body { background: #fff; padding: 0; }
            .toolbar { display: none; }
            .certificate { border: 2px solid #b7a970; border-radius: 0; margin: 0; max-width: 100%; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <button class="print-btn" onclick="window.print()">Print Certificate</button>
    </div>

    @yield('content')
</body>
</html>