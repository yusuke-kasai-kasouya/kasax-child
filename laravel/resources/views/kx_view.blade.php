<!DOCTYPE html>
<html>
<head>
    <title>KX Laravel Test</title>
    <style>
        body { font-family: sans-serif; background: #f4f4f4; padding: 20px; }
        .card { background: white; margin-bottom: 10px; padding: 15px; border-radius: 5px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        h3 { margin: 0; color: #333; }
        p { color: #666; font-size: 0.9em; }
    </style>
</head>
<body>
    <h1>KXデータ表示テスト</h1>
    @foreach($items as $item)
        <div class="card">
            <h3>ID: {{ $item->id }} - {{ $item->title }}</h3>
            <p>日付: {{ $item->text }}</p>
            <div>内容: {{ $item->json }}</div>
        </div>
    @endforeach
</body>
</html>