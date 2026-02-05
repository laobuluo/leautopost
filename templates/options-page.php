<div class="wrap">
    <h1>LeAutoPost设置</h1>
    <p>定时草稿发布插件设置参数。<a href="https://www.lezaiyun.com/854.html" target="_blank">插件介绍</a>（关注公众号：<span style="color: red;">老蒋朋友圈</span>）</p>

    <form method="post" action="">
        <table class="form-table">
            <tr>
                <th scope="row">启用自动发布</th>
                <td>
                    <label>
                        <input type="checkbox" name="enabled" value="1" <?php checked($this->options['enabled']); ?> />
                        启用自动发布草稿文章
                    </label>
                </td>
            </tr>
            <tr>
                <th scope="row">发布频率（秒）</th>
                <td>
                    <input type="number" name="interval" value="<?php echo esc_attr($this->options['interval']); ?>" min="1" />
                    <p class="description">设置自动发布文章的时间间隔，单位为秒，默认为3600秒（1小时）</p>
                </td>
            </tr>
            <tr>
                <th scope="row">时间限制</th>
                <td>
                    <label>
                        <input type="checkbox" name="time_restriction" value="1" <?php checked($this->options['time_restriction']); ?> />
                        启用时间限制
                    </label>
                    <p class="description">设置允许发布文章的时间段（当前WordPress时区：<?php echo esc_html(wp_timezone_string()); ?>）</p>
                    <div style="margin-top: 10px;">
                        <label>开始时间：</label>
                        <input type="time" name="start_time" value="<?php echo esc_attr($this->options['start_time']); ?>" step="1" />
                        <label style="margin-left: 20px;">结束时间：</label>
                        <input type="time" name="end_time" value="<?php echo esc_attr($this->options['end_time']); ?>" step="1" />
                    </div>
                </td>
            </tr>
            <tr>
                <th scope="row">发布数量限制</th>
                <td>
                    <input type="number" name="max_posts" value="<?php echo esc_attr($this->options['max_posts']); ?>" min="1" />
                    <p class="description">设置最大发布文章数量，留空表示不限制</p>
                    <?php if (!empty($this->options['max_posts'])): ?>
                        <p class="description">已发布：<?php echo intval(get_option('le_auto_post_published_count', 0)); ?> / <?php echo intval($this->options['max_posts']); ?></p>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <th scope="row">Cron设置</th>
                <td>
                    <label>
                        <input type="checkbox" name="use_external_cron" value="1" <?php checked($this->options['use_external_cron']); ?> />
                        使用外部Cron（推荐）
                    </label>
                    <p class="description">使用外部Cron可以提高定时发布的可靠性。启用后，请使用以下URL设置系统Cron或第三方定时任务服务：</p>
                    <code style="display: block; margin: 10px 0;"><?php echo esc_url(site_url('?le_auto_post_cron=1&secret=' . $this->options['cron_secret'])); ?></code>
                    <p class="description">建议设置为每小时执行一次。</p>
                </td>
            </tr>
            <tr>
                <th scope="row">过滤关键词</th>
                <td>
                    <textarea name="filter_keywords" rows="5" cols="50"><?php echo esc_textarea($this->options['filter_keywords']); ?></textarea>
                    <p class="description">每行输入一个需要过滤的关键词</p>
                </td>
            </tr>
            <tr>
                <th scope="row">替换字符</th>
                <td>
                    <input type="text" name="replacement_char" value="<?php echo esc_attr($this->options['replacement_char']); ?>" />
                    <p class="description">用于替换被过滤关键词的字符，留空表示直接删除关键词</p>
                </td>
            </tr>
            <tr>
                <th scope="row">内容过滤选项</th>
                <td>
                    <fieldset>
                        <label>
                            <input type="checkbox" name="filter_images" value="1" <?php checked($this->options['filter_images']); ?> />
                            过滤图片
                        </label>
                        <br />
                        <label>
                            <input type="checkbox" name="filter_links" value="1" <?php checked($this->options['filter_links']); ?> />
                            过滤链接
                        </label>
                        <br />
                        <label>
                            <input type="checkbox" name="filter_urls" value="1" <?php checked($this->options['filter_urls']); ?> />
                            过滤URL
                        </label>
                    </fieldset>
                </td>
            </tr>
        </table>
        <p class="submit">
            <input type="submit" name="submit" class="button button-primary" value="保存设置" />
            <button type="button" class="button" id="manual-post-draft">立即发布一篇草稿</button>
        </p>
    </form>

    <h2>发布日志</h2>
    <div id="publish-log" style="background: #fff; padding: 10px; border: 1px solid #ccd0d4; max-height: 300px; overflow-y: auto;">
        <?php
        if (file_exists($this->log_file)) {
            echo '<pre>' . esc_html(file_get_contents($this->log_file)) . '</pre>';
        } else {
            echo '<p>暂无日志记录</p>';
        }
        ?>
    </div>

    <script type="text/javascript">
    jQuery(document).ready(function($) {
        $('#manual-post-draft').click(function() {
            var button = $(this);
            button.prop('disabled', true);
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'manual_post_draft',
                    nonce: '<?php echo wp_create_nonce("le_auto_post_nonce"); ?>'
                },
                success: function(response) {
                    if (response.success) {
                        alert('发布成功！');
                        location.reload();
                    } else {
                        alert(response.data);
                    }
                },
                error: function() {
                    alert('操作失败，请重试');
                },
                complete: function() {
                    button.prop('disabled', false);
                }
            });
        });
    });
    </script>
</div>