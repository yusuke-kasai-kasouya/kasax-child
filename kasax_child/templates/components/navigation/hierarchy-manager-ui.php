<?php
/**
 * 階層管理UIテンプレート（Virtualリンク追加 & スタイル更新版）
 */
?>
<div class="kx-hierarchy-ui" style="background:#1e1e1e; color:#ccc; padding:12px; border-radius:5px; font-family:monospace; margin-bottom:15px; border:1px solid #333;">

    <div style="display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid #444; padding-bottom:8px; margin-bottom:12px;">
        <strong style="color:#569cd6;">
            <a href="<?php echo esc_url(admin_url('admin-ajax.php?action=kx_hierarchy_action&mode=full&base=' . urlencode($base_title))); ?>"
               target="_blank"
               title="このVirtual階層を全画面表示"
               style="color:#569cd6; text-decoration:none; border-bottom:1px dashed #569cd6;">
               <?php echo esc_html($base_title); ?> ≫
            </a>
        </strong>
    </div>

    <div class="kx-button-group" style="display:flex; flex-direction:column; gap:6px; margin-bottom:12px;">
        <?php
            $btn_style = "cursor:pointer; font-size:11px; background:#000; color:#ddd; border-width:1px; border-style:solid; padding:4px 8px; border-radius:3px; text-align:left; transition:0.2s;";
        ?>
        <button onclick="kx_h_ajax('full', '<?php echo esc_js($base_title); ?>', '<?php echo $unique_id; ?>')"
                style="<?php echo $btn_style; ?> border-color:#569cd6;"
                onmouseover="this.style.background='#111'" onmouseout="this.style.background='#000'">
            全下位表示
        </button>

        <button onclick="kx_h_ajax('repair_sub', '<?php echo esc_js($base_title); ?>', '<?php echo $unique_id; ?>')"
                style="<?php echo $btn_style; ?> border-color:#0e639c;"
                onmouseover="this.style.background='#111'" onmouseout="this.style.background='#000'">
            子階層表示（メンテ）
        </button>

        <button onclick="kx_h_ajax('repair_all', '<?php echo esc_js($base_title); ?>', '<?php echo $unique_id; ?>')"
                style="<?php echo $btn_style; ?> border-color:#a31515;"
                onmouseover="this.style.background='#111'" onmouseout="this.style.background='#000'">
            全下位表示（メンテ）
        </button>
    </div>

    <pre id="<?php echo $unique_id; ?>" style="margin:0; white-space:pre-wrap; line-height:1.4; color:#9cdcfe; font-size:11px; background:#111; padding:8px; border-radius:3px; border:1px solid #222;">ボタンを押すと読み込みます...</pre>

    <script>
    if(typeof kx_h_ajax !== "function"){
        function kx_h_ajax(mode, base, targetId) {
            const out = document.getElementById(targetId);
            out.innerHTML = "<span style='color:#666;'>処理中...</span>";
            const url = "<?php echo esc_js($admin_ajax_url); ?>";
            const params = new URLSearchParams({ action: "kx_hierarchy_action", mode: mode, base: base });

            fetch(url + "?" + params.toString())
                .then(res => { if (!res.ok) throw new Error(res.status); return res.text(); })
                .then(data => { out.innerHTML = data || "階層データなし"; })
                .catch(err => { out.innerText = "Error: " + err.message; });
        }
    }
    </script>
</div>