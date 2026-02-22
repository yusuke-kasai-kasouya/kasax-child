<?php
/**
 * $messages : array (KxMessage::render から渡される変数)
 */
if (empty($messages)) return;

$error_count = 0;
foreach($messages as $m) if(in_array($m['type'], ['error', 'warn'])) $error_count++;
?>

<div id="kx-message-hub">
    <?php if ($error_count > 0): ?>
    <div id="kx-error-window" class="kx-error-window">
        <div class="kx-error-header" onclick="toggleKxErrorWindow()">
            <span class="kx-title">ERRORS / WARNINGS (<?php echo $error_count; ?>)</span>
            <button class="kx-min-btn">－</button>
        </div>
        <div id="kx-error-stack-body" class="kx-error-body">
            <?php foreach ($messages as $m): ?>
                <?php if (in_array($m['type'], ['error', 'warn'])): ?>
                    <div class="kx-msg kx-type-<?php echo esc_attr($m['type']); ?>">
                        <span class="kx-icon"><?php echo $m['type'] === 'error' ? '✘' : '⚠'; ?></span>
                        <div class="kx-text">
                            <?php if (is_array($m['text'])): ?>
                                <details>
                                    <summary>Data Log (Array)</summary>
                                    <pre><?php echo esc_html(json_encode($m['text'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)); ?></pre>
                                </details>
                            <?php else: ?>
                                <?php echo nl2br($m['text']); ?>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <div id="kx-info-stack">
        <?php foreach ($messages as $m): ?>
            <?php if (!in_array($m['type'], ['error', 'warn'])): ?>
                <?php
                    // タイプごとに消去秒数を設定
                    $duration = 5000;
                    if ($m['type'] === 'caution') $duration = 5000;
                    if ($m['type'] === 'notice')  $duration = 3000;
                    if ($m['type'] === 'info')    $duration = 1000;
                ?>
                <div class="kx-msg kx-type-<?php echo esc_attr($m['type']); ?>"
                  data-autoclose="<?php echo $duration; ?>">
                    <span class="kx-icon">i</span>
                    <span class="kx-text"><?php echo is_array($m['text']) ? 'Info Data Logged' : $m['text']; ?></span>
                </div>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>
</div>

<style>
#kx-message-hub { font-family: sans-serif; }

/* --- 右下ウィンドウ（ダークモード最適化） --- */
.kx-error-window {
    position: fixed;
    bottom: 5px; right: 5px;
    width: 380px; max-height: 400px;
    background: #1e1e1e; /* 背景をダークに */
    border: 2px solid #444; /* 境界線を少し明るく */
    z-index: 10000; display: flex;
    flex-direction: column;
    box-shadow: 0 10px 30px rgba(0,0,0,0.7);
}
.kx-error-window.is-minimized {
    height: 40px; width: 280px; overflow: hidden;
}
.kx-min-btn{
    padding: 2px 8px; /* 上下2px、左右8px */
  font-size: 12px;   /* 文字自体も小さくするとよりコンパクトに */
}


.kx-error-header {
    background: #000; color: #fff;
    padding: 5px 10px;
    display: flex; justify-content: space-between; cursor: pointer;
    border-bottom: 1px solid #333;
}

.kx-error-body {
    padding: 10px; overflow-y: auto; background: #111; /* ボディをより深く */
    display: flex; flex-direction: column; gap: 8px;
}

/* --- 左下通知エリア --- */
#kx-info-stack {
    position: fixed; bottom: 0px; left: 0px; /* 少し浮かせて視認性UP */
    z-index: 10001; pointer-events: none;
}
#kx-info-stack .kx-msg {
    pointer-events: auto; margin-top: 1px; width: 280px;
    padding: 6px 10px; /* パディングを凝縮 */
    box-shadow: 0 4px 15px rgba(0,0,0,0.5);
}

/* --- メッセージカード本体 --- */
.kx-msg {
    display: flex; gap: 8px; padding: 12px;
    border-radius: 4px; border: 1px solid #444;
    background: #1e1e1e; color: #fff !important;
    font-size: 13px; font-weight: 600; line-height: 1.3;
    animation: kx-fade 0.3s ease;
}

/* タイプ別：ダークモード用背景色 */
.kx-type-error   { border-left: 8px solid #ff3333; background: #2a1010; color: #ffdada !important; }
.kx-type-warn    { border-left: 8px solid #ffcc00; background: #2a2510; color: #fff4cc !important; }
.kx-type-caution { border-left: 8px solid #ff00ea; background: #25102a; color: #fce4ff !important; }
.kx-type-notice  { border-left: 8px solid #8bc34a; background: #142010; color: #e2f4df !important; }
.kx-type-info    { border-left: 8px solid #2196f3; background: #101a2a; color: #ddecff !important; }

.kx-icon { font-weight: 900; }
.kx-text pre { background: #000; color: #0f0; padding: 8px; font-size: 11px; margin-top: 5px; border: 1px solid #333; }

@keyframes kx-fade { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
</style>

<script>
function toggleKxErrorWindow() {
    const win = document.getElementById('kx-error-window');
    win.classList.toggle('is-minimized');
    win.querySelector('.kx-min-btn').textContent = win.classList.contains('is-minimized') ? '＋' : '－';
}
document.addEventListener('DOMContentLoaded', function() {
    // 自動消去ロジックの修正
    setInterval(() => {
        document.querySelectorAll('#kx-info-stack .kx-msg[data-autoclose]').forEach(el => {
            if(!el.dataset.start) el.dataset.start = Date.now();

            // PHP側で設定した時間を取得
            const duration = parseInt(el.dataset.autoclose) || 5000;

            if(Date.now() - el.dataset.start > duration) {
                el.style.opacity = '0';
                el.style.transition = 'opacity 0.5s ease';
                setTimeout(() => el.remove(), 500);
            }
        });
    }, 500);
});
</script>