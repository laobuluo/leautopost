<?php
/**
 * Plugin Name: LeAutoPost
 * Plugin URI: https://www.lezaiyun.com/854.html
 * Description: 一款可以实现自动将WordPress草稿文章定时发布的插件。公众号：老蒋朋友圈。
 * Version: 1.0.1
 * Author: 老蒋和他的小伙伴
 * Author URI: https://www.lezaiyun.com
 */

if (!defined('ABSPATH')) {
    exit;
}

class LeAutoPost {
    private static $instance = null;
    private $options;
    private $log_file;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // 加载插件选项
        $this->options = get_option('le_auto_post_options', array(
            'enabled' => false,
            'interval' => 3600,
            'filter_keywords' => '',
            'replacement_char' => '',
            'filter_images' => false,
            'filter_links' => false,
            'filter_urls' => false,
            'use_external_cron' => false,
            'cron_secret' => bin2hex(random_bytes(16)), // 生成32个字符的随机密码
            'time_restriction' => false,
            'start_time' => '00:00:00',
            'end_time' => '23:59:59',
            'max_posts' => '',
            'last_post_time' => 0 // 添加最后发布时间记录
        ));
        
        $this->log_file = plugin_dir_path(__FILE__) . 'le-auto-post.log';
        
        // 添加管理菜单
        add_action('admin_menu', array($this, 'add_admin_menu'));
        // 注册设置
        add_action('admin_init', array($this, 'register_settings'));
        // 注册定时任务钩子
        if (!$this->options['use_external_cron']) {
            add_action('le_auto_post_cron', array($this, 'auto_post_drafts'));
        }
        // 添加外部Cron访问接口
        add_action('init', array($this, 'handle_external_cron'));
        // 添加AJAX处理
        add_action('wp_ajax_manual_post_draft', array($this, 'manual_post_draft'));
    }
    
    public function activate() {
        // 确保所有选项都有默认值
        $default_options = array(
            'enabled' => false,
            'interval' => 3600,
            'filter_keywords' => '',
            'replacement_char' => '',
            'filter_images' => false,
            'filter_links' => false,
            'filter_urls' => false,
            'use_external_cron' => false,
            'cron_secret' => bin2hex(random_bytes(16)),
            'time_restriction' => false,
            'start_time' => '00:00:00',
            'end_time' => '23:59:59',
            'max_posts' => '',
            'last_post_time' => 0
        );
        
        // 合并现有选项和默认选项
        $current_options = get_option('le_auto_post_options', array());
        $this->options = array_merge($default_options, $current_options);
        update_option('le_auto_post_options', $this->options);
        
        // 设置定时任务
        if (!$this->options['use_external_cron']) {
            if (!wp_next_scheduled('le_auto_post_cron')) {
                wp_schedule_event(time(), 'hourly', 'le_auto_post_cron');
            }
        }
    }
    
    public function deactivate() {
        // 清除定时任务
        wp_clear_scheduled_hook('le_auto_post_cron');
    }
    
    public function uninstall() {
        // 删除插件选项和日志文件
        delete_option('le_auto_post_options');
        if (file_exists($this->log_file)) {
            unlink($this->log_file);
        }
    }
    
    public function add_admin_menu() {
        add_options_page(
            '自动发布设置',
            'LeAutoPost设置',
            'manage_options',
            'le-auto-post',
            array($this, 'options_page')
        );
    }
    
    public function register_settings() {
        register_setting('le_auto_post', 'le_auto_post_options');
    }
    
    public function options_page() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        if (isset($_POST['submit'])) {
            $this->options['enabled'] = isset($_POST['enabled']);
            $this->options['interval'] = intval($_POST['interval']);
            $this->options['filter_keywords'] = sanitize_textarea_field($_POST['filter_keywords']);
            $this->options['replacement_char'] = sanitize_text_field($_POST['replacement_char']);
            $this->options['filter_images'] = isset($_POST['filter_images']);
            $this->options['filter_links'] = isset($_POST['filter_links']);
            $this->options['filter_urls'] = isset($_POST['filter_urls']);
            $this->options['use_external_cron'] = isset($_POST['use_external_cron']);
            $this->options['time_restriction'] = isset($_POST['time_restriction']);
            $this->options['start_time'] = sanitize_text_field($_POST['start_time']);
            $this->options['end_time'] = sanitize_text_field($_POST['end_time']);
            $this->options['max_posts'] = sanitize_text_field($_POST['max_posts']);
            
            // 如果切换了Cron模式，需要重新设置定时任务
            if ($this->options['use_external_cron']) {
                wp_clear_scheduled_hook('le_auto_post_cron');
            } else {
                if (!wp_next_scheduled('le_auto_post_cron')) {
                    wp_schedule_event(time(), 'hourly', 'le_auto_post_cron');
                }
            }
            
            update_option('le_auto_post_options', $this->options);
            echo '<div class="updated"><p>设置已保存。</p></div>';
        }
        
        include_once plugin_dir_path(__FILE__) . 'templates/options-page.php';
    }
    
    private function log($message) {
        // 检查日志文件
        $max_size = 10 * 1024 * 1024; // 10MB
        $max_days = 3; // 3天

        if (file_exists($this->log_file)) {
            // 检查文件修改时间
            $file_time = filemtime($this->log_file);
            if ((time() - $file_time) > ($max_days * 86400)) {
                unlink($this->log_file);
            }
            // 检查文件大小
            elseif (filesize($this->log_file) > $max_size) {
                unlink($this->log_file);
            }

            // 检查日志时间
            $log_content = file_get_contents($this->log_file);
            $log_lines = explode("\n", $log_content);
            $new_lines = array();
            $cutoff_date = strtotime("-{$max_days} days");

            foreach ($log_lines as $line) {
                if (empty($line)) continue;
                if (preg_match('/\[(.*?)\]/', $line, $matches)) {
                    $log_date = strtotime($matches[1]);
                    if ($log_date > $cutoff_date) {
                        $new_lines[] = $line;
                    }
                }
            }

            if (count($new_lines) < count($log_lines)) {
                file_put_contents($this->log_file, implode("\n", $new_lines) . "\n");
            }
        }

        $timestamp = current_time('mysql');
        $log_message = sprintf("[%s] %s\n", $timestamp, $message);
        error_log($log_message, 3, $this->log_file);
    }
    
    public function handle_external_cron() {
        if (!isset($_GET['le_auto_post_cron']) || !isset($_GET['secret'])) {
            return;
        }
        
        if ($_GET['secret'] !== $this->options['cron_secret']) {
            wp_die('Invalid secret key');
        }
        
        $this->auto_post_drafts();
        wp_die('Cron executed successfully');
    }
    
    public function manual_post_draft() {
        check_ajax_referer('le_auto_post_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('权限不足');
        }
        
        $result = $this->auto_post_drafts();
        if ($result) {
            wp_send_json_success('文章发布成功');
        } else {
            wp_send_json_error('没有可发布的草稿或发布失败');
        }
    }
    
    private function is_within_time_restriction() {
        if (!$this->options['time_restriction']) {
            return true;
        }

        // 获取当前时间（考虑WordPress时区）
        $current_datetime = current_time('Y-m-d H:i:s');
        $current_timestamp = strtotime($current_datetime);
        
        // 获取今天的日期
        $today_date = current_time('Y-m-d');
        
        // 解析开始和结束时间
        $start_time = $this->options['start_time'];
        $end_time = $this->options['end_time'];
        
        // 确保时间格式正确
        if (!preg_match('/^\d{2}:\d{2}:\d{2}$/', $start_time)) {
            $start_time = '00:00:00';
        }
        if (!preg_match('/^\d{2}:\d{2}:\d{2}$/', $end_time)) {
            $end_time = '23:59:59';
        }
        
        // 计算今天的开始和结束时间戳
        $start_timestamp = strtotime($today_date . ' ' . $start_time);
        $end_timestamp = strtotime($today_date . ' ' . $end_time);
        
        // 处理跨日期的情况
        if ($end_timestamp <= $start_timestamp) {
            // 如果当前时间大于等于开始时间，结束时间应该是明天
            if ($current_timestamp >= strtotime($today_date . ' ' . $start_time)) {
                $end_timestamp = strtotime('+1 day', strtotime($today_date . ' ' . $end_time));
            } else {
                // 如果当前时间小于开始时间，开始时间应该是昨天
                $start_timestamp = strtotime('-1 day', strtotime($today_date . ' ' . $start_time));
            }
        }
        
        // 检查时间间隔限制
        if (!empty($this->options['interval'])) {
            $last_post_time = intval($this->options['last_post_time']);
            $min_next_post_time = $last_post_time + intval($this->options['interval']);
            
            if ($current_timestamp < $min_next_post_time) {
                $this->log(sprintf('未达到最小发布间隔，需等待至：%s', 
                    date('Y-m-d H:i:s', $min_next_post_time)));
                return false;
            }
        }
        
        $is_within = ($current_timestamp >= $start_timestamp && $current_timestamp <= $end_timestamp);
        
        if (!$is_within) {
            $this->log(sprintf('当前时间 %s 不在允许的发布时间范围内 (%s - %s)', 
                date('Y-m-d H:i:s', $current_timestamp),
                date('Y-m-d H:i:s', $start_timestamp),
                date('Y-m-d H:i:s', $end_timestamp)));
        }
        
        return $is_within;
    }
    
    public function auto_post_drafts() {
        if (!$this->options['enabled']) {
            $this->log('自动发布功能未启用');
            return false;
        }
        
        // 检查时间限制
        if (!$this->is_within_time_restriction()) {
            return false;
        }

        // 检查发布数量限制
        if (!empty($this->options['max_posts'])) {
            $published_count = get_option('le_auto_post_published_count', 0);
            if ($published_count >= intval($this->options['max_posts'])) {
                $this->log('已达到最大发布数量限制');
                return false;
            }
        }
        
        $args = array(
            'post_type' => 'post',
            'post_status' => 'draft',
            'posts_per_page' => 1,
            'orderby' => 'date',
            'order' => 'ASC'
        );
        
        $drafts = get_posts($args);
        
        if (empty($drafts)) {
            $this->log('没有找到待发布的草稿');
            return false;
        }
        
        foreach ($drafts as $draft) {
            $this->log(sprintf('开始处理草稿: ID=%d, 标题=%s', $draft->ID, $draft->post_title));
            
            $content = $draft->post_content;
            
            // 处理关键词过滤
            if (!empty($this->options['filter_keywords'])) {
                $keywords = explode("\n", $this->options['filter_keywords']);
                $replacement = $this->options['replacement_char'];
                foreach ($keywords as $keyword) {
                    $keyword = trim($keyword);
                    if (!empty($keyword)) {
                        $content = str_replace($keyword, $replacement, $content);
                    }
                }
            }
            
            // 过滤图片
            if ($this->options['filter_images']) {
                $content = preg_replace('/<img[^>]+>/i', '', $content);
            }
            
            // 过滤链接
            if ($this->options['filter_links']) {
                $content = preg_replace('/<a[^>]+>.*?<\/a>/i', '', $content);
            }
            
            // 过滤URL
            if ($this->options['filter_urls']) {
                $content = preg_replace('/https?:\/\/[\w\-\.\/?=&]+/i', '', $content);
            }
            
            // 更新文章内容
            $post_data = array(
                'ID' => $draft->ID,
                'post_content' => $content,
                'post_status' => 'publish'
            );
            
            $result = wp_update_post($post_data);
            
            if ($result) {
                $this->log(sprintf('文章发布成功: ID=%d', $draft->ID));
                
                // 更新最后发布时间
                $this->options['last_post_time'] = time();
                update_option('le_auto_post_options', $this->options);
                
                // 更新发布计数
                if (!empty($this->options['max_posts'])) {
                    $published_count = get_option('le_auto_post_published_count', 0);
                    update_option('le_auto_post_published_count', $published_count + 1);
                }
                
                return true;
            } else {
                $this->log(sprintf('文章发布失败: ID=%d', $draft->ID));
                return false;
            }
        }
        
        return false;
    }
}

// 初始化插件
$le_auto_post = LeAutoPost::get_instance();

// 注册激活、停用和卸载钩子
register_activation_hook(__FILE__, array($le_auto_post, 'activate'));
register_deactivation_hook(__FILE__, array($le_auto_post, 'deactivate'));
register_uninstall_hook(__FILE__, array($le_auto_post, 'uninstall'));