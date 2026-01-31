(function($) {
    'use strict';

    console.log('CNBlogs Sync: admin.js loaded');

    if (typeof $ === 'undefined') {
        console.error('CNBlogs Sync: jQuery undefined');
        return;
    }

    if (typeof cnblogs_sync_data === 'undefined') {
        console.warn('CNBlogs Sync: Data object undefined');
    } else {
        console.log('CNBlogs Sync: Data object available', cnblogs_sync_data);
    }

    $(document).ready(function() {
        console.log('CNBlogs Sync: DOM ready, setting up event listeners');

        $(document).on('click', '#cnblogs_test_connection', function(e) {
            e.preventDefault();
            e.stopPropagation();
            console.log('CNBlogs Sync: Test connection button clicked');
            testConnection();
        });

        $(document).on('click', '.cnblogs-sync-btn', function(e) {
            e.preventDefault();
            const postId = $(this).data('post-id');
            console.log('CNBlogs Sync: Sync button clicked for post', postId);
            if (postId) {
                syncSinglePost(postId);
            }
        });

        $(document).on('click', '.cnblogs-quick-sync', function(e) {
            e.preventDefault();
            const postId = $(this).data('post-id');
            console.log('CNBlogs Sync: Quick sync link clicked for post', postId);
            if (postId) {
                quickSyncPost(postId, $(this));
            }
        });
    });

    function testConnection() {
        if (typeof cnblogs_sync_data === 'undefined' || !cnblogs_sync_data.ajax_url) {
            alert('Data load failed, please refresh and try again');
            return;
        }

        const button = $('#cnblogs_test_connection');
        const result = $('#connection_test_result');
        const username = $('#cnblogs_sync_username').val();
        const password = $('#cnblogs_sync_password').val();

        if (!username) {
            result.html('<div style="color: #d63638; margin-top: 8px;">Error: Please enter CNBlogs username</div>');
            return;
        }

        if (!password) {
            result.html('<div style="color: #d63638; margin-top: 8px;">Error: Please enter MetaWeblog token</div>');
            return;
        }

        button.prop('disabled', true);
        const originalText = button.text();
        button.text('Testing...');
        result.html('<div style="color: #1e90ff; margin-top: 8px;">Testing connection...</div>');

        $.ajax({
            url: cnblogs_sync_data.ajax_url,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'cnblogs_test_connection',
                nonce: cnblogs_sync_data.nonce,
                username: username,
                password: password
            },
            success: function(response) {
                if (response.success) {
                    // Check if there's a warning (empty blog list but connection successful)
                    if (response.data && response.data.warning) {
                        result.html('<div style="color: #ff6600; margin-top: 8px;"><strong>Warning:</strong> ' + response.data.message + '</div>');
                    } else {
                        result.html('<div style="color: #008000; margin-top: 8px; font-weight: bold;">✓ ' + (response.data && response.data.message ? response.data.message : 'Connection successful!') + '</div>');
                    }
                } else {
                    const errorData = response.data ? response.data : 'Connection failed';
                    result.html('<div style="color: #d63638; margin-top: 8px;">✗ Error: ' + errorData + '</div>');
                }
            },
            error: function(xhr, status, error) {
                result.html('<div style="color: #d63638; margin-top: 8px;">Error: Network request failed (HTTP ' + xhr.status + ')</div>');
            },
            complete: function() {
                button.prop('disabled', false).text(originalText);
            }
        });
    }

    function syncSinglePost(postId) {
        console.log('CNBlogs Sync: syncSinglePost called for post', postId);

        if (typeof cnblogs_sync_data === 'undefined' || !cnblogs_sync_data.ajax_url) {
            console.error('CNBlogs Sync: cnblogs_sync_data not available');
            alert('Data load failed, please refresh and try again');
            return;
        }

        const button = $('.cnblogs-sync-btn[data-post-id="' + postId + '"]');
        if (button.length === 0) {
            console.error('CNBlogs Sync: Button not found for post', postId);
            return;
        }

        const originalText = button.text();
        button.prop('disabled', true).text('Syncing...');

        console.log('CNBlogs Sync: Sending sync request for post', postId);

        $.ajax({
            url: cnblogs_sync_data.ajax_url,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'cnblogs_sync_single_post',
                nonce: cnblogs_sync_data.nonce,
                post_id: postId
            },
            success: function(response) {
                console.log('CNBlogs Sync: Response received', response);
                if (response.success) {
                    showNotice(response.data, 'success');
                    updateSyncStatus(postId);
                } else {
                    showNotice(response.data || 'Sync failed', 'error');
                }
            },
            error: function(xhr, status, error) {
                console.error('CNBlogs Sync: AJAX error', status, error, xhr.responseText);
                showNotice('Network error: ' + error, 'error');
            },
            complete: function() {
                button.prop('disabled', false).text(originalText);
            }
        });
    }

    function quickSyncPost(postId, linkElement) {
        if (typeof cnblogs_sync_data === 'undefined' || !cnblogs_sync_data.ajax_url) {
            console.error('CNBlogs Sync: cnblogs_sync_data not available');
            alert('Data load failed, please refresh and try again');
            return;
        }

        // 获取按钮上的 nonce（如果有的话），否则使用全局的 nonce
        const nonce = linkElement.data('nonce') || cnblogs_sync_data.nonce;
        if (!nonce) {
            console.error('CNBlogs Sync: nonce not available');
            alert('Security check failed, please refresh and try again');
            return;
        }

        const originalText = linkElement.text();
        linkElement.text('同步中...');
        linkElement.prop('disabled', true);

        console.log('CNBlogs Sync: Sending sync request for post ' + postId);

        $.ajax({
            url: cnblogs_sync_data.ajax_url,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'cnblogs_sync_single_post',
                nonce: nonce,
                post_id: postId
            },
            success: function(response) {
                console.log('CNBlogs Sync: Response received', response);
                if (response.success) {
                    // Show inline success message
                    linkElement.html('✓ 已同步');
                    linkElement.css('color', '#008000');
                    // Reload page after 2 seconds to show updated status
                    setTimeout(function() {
                        location.reload();
                    }, 2000);
                } else {
                    alert('同步失败: ' + (response.data || '未知错误'));
                    linkElement.text(originalText);
                    linkElement.prop('disabled', false);
                }
            },
            error: function(xhr, status, error) {
                console.error('CNBlogs Sync: AJAX error', status, error, xhr.responseText);
                alert('网络错误: ' + error);
                linkElement.text(originalText);
                linkElement.prop('disabled', false);
            }
        });
    }

    function updateSyncStatus(postId) {
        if (typeof cnblogs_sync_data === 'undefined' || !cnblogs_sync_data.ajax_url) {
            return;
        }

        $.ajax({
            url: cnblogs_sync_data.ajax_url,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'cnblogs_get_sync_status',
                nonce: cnblogs_sync_data.nonce,
                post_id: postId
            },
            success: function(response) {
                if (response.success) {
                    setTimeout(function() {
                        location.reload();
                    }, 1000);
                }
            }
        });
    }

    function showNotice(message, type) {
        type = type || 'info';
        
        const wrapElement = document.querySelector('.wrap');
        if (!wrapElement) {
            return;
        }
        
        const notice = $('<div>')
            .addClass('notice notice-' + type + ' is-dismissible')
            .html('<p>' + htmlEscape(message) + '</p>');
        
        const closeBtn = $('<button>')
            .attr('type', 'button')
            .addClass('notice-dismiss')
            .html('<span class="screen-reader-text">Dismiss</span>')
            .on('click', function() {
                notice.fadeOut(function() {
                    $(this).remove();
                });
            });
        
        notice.append(closeBtn);
        notice.prependTo('.wrap');
        
        setTimeout(function() {
            notice.fadeOut(function() {
                $(this).remove();
            });
        }, 5000);
    }
    
    function htmlEscape(text) {
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return String(text).replace(/[&<>"']/g, function(char) {
            return map[char];
        });
    }

})(jQuery);
