<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>創作ナレッジ検索 - 入力</title>
    <style>
        body { font-family: sans-serif; padding: 20px; line-height: 1.6; background: #f4f4f4; }
        .container { max-width: 600px; margin: auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        input[type="text"] { width: 70%; padding: 10px; font-size: 16px; }
        button { padding: 10px 20px; font-size: 16px; cursor: pointer; background: #007bff; color: white; border: none; border-radius: 4px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>キーワード検索</h1>
        <p>文脈を抜き出します。</p>

        <form action="/search-tool" method="POST">
            @csrf
            <input type="text" name="keyword" placeholder="検索" required>

            <br><br>

            <label>カテゴリ：</label>
            <select name="category_id">
                <option value="">指定なし</option>
                <option value="76">76：δ研究 </option>
                <option value="325">325：∮アイデア</option>
            </select>

            <br><br>

            <button type="submit">検索</button>
        </form>
    </div>
</body>
</html>
