<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 28px 30px; }
        body { font-family: DejaVu Sans, sans-serif; color: #0f172a; font-size: 10px; line-height: 1.45; }
        h1, h2, h3 { margin: 0; color: #0a2540; }
        h1 { font-size: 24px; }
        h2 { font-size: 15px; margin: 18px 0 8px; border-bottom: 2px solid #0a2540; padding-bottom: 4px; }
        h3 { font-size: 12px; margin: 12px 0 6px; }
        .muted { color: #64748b; }
        .cover { padding: 50px 0 26px; border-bottom: 4px solid #0a2540; }
        .brand { font-size: 12px; letter-spacing: 2px; color: #0a2540; font-weight: bold; }
        .cover h1 { margin-top: 18px; font-size: 30px; }
        .cover .dealer { margin-top: 8px; font-size: 14px; color: #334155; }
        .meta { margin-top: 22px; width: 100%; }
        .meta td { padding: 3px 0; vertical-align: top; }
        .meta td:first-child { width: 110px; color: #64748b; }
        .kpi { width: 100%; margin-top: 12px; border-collapse: collapse; }
        .kpi td { width: 25%; padding: 9px; border: 1px solid #e5e7eb; background: #f8fafc; }
        .kpi .label { color: #64748b; font-size: 8px; text-transform: uppercase; letter-spacing: .5px; }
        .kpi .value { font-size: 14px; font-weight: bold; margin-top: 3px; }
        table.data { width: 100%; border-collapse: collapse; margin-top: 6px; }
        table.data th { background: #0a2540; color: #fff; text-align: left; padding: 5px; font-size: 8px; }
        table.data td { border-bottom: 1px solid #e5e7eb; padding: 5px; vertical-align: top; }
        .right { text-align: right; }
        .photos { width: 100%; border-collapse: collapse; margin-top: 6px; }
        .photos td { width: 50%; padding: 4px; vertical-align: top; }
        .photo { width: 100%; height: 150px; object-fit: cover; border: 1px solid #e5e7eb; }
        .caption { font-size: 8px; color: #475569; margin-top: 3px; }
        .badge { display: inline-block; padding: 2px 6px; border-radius: 8px; background: #e9eff7; color: #0a2540; font-size: 8px; }
        .note { background: #f8fafc; border-left: 3px solid #0a2540; padding: 7px 9px; margin-top: 6px; }
        .page-break { page-break-before: always; }
        .footer { margin-top: 24px; border-top: 1px solid #e5e7eb; padding-top: 8px; font-size: 8px; color: #64748b; }
        .sign { width: 100%; margin-top: 30px; border-collapse: collapse; }
        .sign td { width: 33%; text-align: center; padding-top: 40px; border-top: 1px solid #94a3b8; font-size: 9px; }
        .pdf-watermark {
            position: fixed;
            top: -90px;
            left: -150px;
            z-index: 999;
            width: 1100px;
            opacity: 0.07;
            color: #0a2540;
            transform: rotate(-28deg);
        }
        .pdf-wm-row {
            position: absolute;
            left: 0;
            width: 1400px;
            white-space: nowrap;
        }
        .pdf-wm-item {
            display: inline-block;
            margin-right: 44px;
            vertical-align: top;
        }
        .pdf-wm-line1 {
            display: block;
            font-size: 10px;
            font-weight: bold;
        }
        .pdf-wm-line2 {
            display: block;
            font-size: 8px;
        }
    </style>
</head>
<body>
    @foreach ($sectionOrder as $section)
        @includeIf('reports.partials.'.$section)
    @endforeach

    @if (! empty($watermark['enabled']))
        <div class="pdf-watermark">
            @for ($row = 0; $row < 13; $row++)
                <div class="pdf-wm-row" style="top: {{ $row * 105 }}px">
                    @for ($col = 0; $col < 4; $col++)
                        <span class="pdf-wm-item">
                            <span class="pdf-wm-line1">{{ $watermark['line1'] }}</span>
                            <span class="pdf-wm-line2">{{ $watermark['line2'] }}</span>
                        </span>
                    @endfor
                </div>
            @endfor
        </div>
    @endif
</body>
</html>
