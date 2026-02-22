<h1>「{{ $keyword }}」の出現箇所一覧（{{ $count }} 件）</h1>

<a href="/search-tool">再検索</a>
<hr>

<ul>
   @foreach($results as $result)
        <div>
            <h3><a href="{{ $result['link'] }}" target="_blank">{{ $result['title'] }}</a></h3>
            <p>...{{ $result['excerpt'] }}...</p>
            <small>URL: {{ $result['link'] }}</small>
        </div>
        <hr>
    @endforeach
</ul>


