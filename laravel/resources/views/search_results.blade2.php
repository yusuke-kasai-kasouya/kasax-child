{{-- resources/views/search_results.blade.php --}}
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>検索結果 - {{ $keyword }}</title>
    <style>
        body { font-family: "MS UI Gothic", sans-serif; padding: 20px; background: #fff; }
        .result-item { margin-bottom: 20px; border-bottom: 1px dotted #ccc; padding-bottom: 10px; }
        .title { font-weight: bold; color: #0056b3; text-decoration: none; }
        .excerpt { display: block; background: #f9f9f9; padding: 10px; margin-top: 5px; font-size: 0.9em; border-left: 4px solid #ddd; }
        .hit { color: red; font-weight: bold; }
    </style>
</head>
<body>
    <h1>「{{ $keyword }}」の出現箇所： {{ $results->count() }} 件</h1>
    <a href="/search-tool">← 戻る</a>
    <hr>

    @foreach ($results as $item)
        <div class="result-item">
            {{-- WordPressのURLに合わせて調整してください --}}
            <a class="title" href="/?p={{ $item['id'] }}" target="_blank">
                ID:{{ $item['id'] }} - {{ $item['title'] }}
            </a>
            <span class="excerpt">
                ...{!! str_replace($keyword, '<span class="hit">'.$keyword.'</span>', e($item['excerpt'])) !!}...
            </span>
        </div>
    @endforeach

    @if($results->isEmpty())
        <p>該当する箇所は見つかりませんでした。</p>
    @endif
</body>
</html>
