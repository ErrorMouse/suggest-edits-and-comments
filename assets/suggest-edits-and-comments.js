jQuery(document).ready(function($) {
    $('body').append(`
        <div id="suggest-edits-tooltip" style="display:none; position:absolute; z-index:9999;">
            <button id="suggest-edits-btn">${seaco_vars.i18n.suggest_edit}</button>
        </div>
        <div id="suggest-edits-form-box" style="display:none; position:absolute; z-index:10000;">
            <textarea id="suggest-edits-content" placeholder="${seaco_vars.i18n.placeholder}"></textarea>
            <div style="display: flex; flex-wrap: wrap; gap: 5px;">
                <button id="suggest-edits-submit">${seaco_vars.i18n.submit}</button>
                <button id="suggest-edits-cancel">${seaco_vars.i18n.cancel}</button>
            </div>
        </div>
        <div id="suggest-edits-toast"></div>
    `);

    // --- TOAST MESSAGE ---
    let toastTimeout;
    function showToast(message, isError = false) {
        let $toast = $('#suggest-edits-toast');
        $toast.text(message);
        $toast.removeClass('error success').addClass(isError ? 'error' : 'success');
        $toast.addClass('show');
        
        clearTimeout(toastTimeout);
        toastTimeout = setTimeout(function() {
            $toast.removeClass('show');
        }, 3000); 
    }
    // ---------------------

    let selectedText = '';
    let selectedPrefix = '';
    let selectedSuffix = '';
    let targetSelectors = seaco_vars.target_selectors || '.entry-content';

    function getContextText(node, offset, isPrefix) {
        let text = isPrefix ? (node.nodeType === 3 ? node.nodeValue.substring(0, offset) : '') : (node.nodeType === 3 ? node.nodeValue.substring(offset) : '');
        let curr = node;
        let count = 0;
        
        while (text.trim().split(/[\s\n]+/).length < 5 && curr && !$(curr).is(targetSelectors) && count < 15) {
            let sibling = isPrefix ? curr.previousSibling : curr.nextSibling;
            if (sibling) {
                let siblingText = sibling.textContent || '';
                text = isPrefix ? siblingText + " " + text : text + " " + siblingText;
                curr = sibling;
            } else {
                curr = curr.parentNode;
            }
            count++;
        }
        let words = text.trim().split(/[\s\n]+/).filter(w => w.length > 0);
        
        // Lấy 1 từ trước (Prefix) và 1 từ sau (Suffix)
        return isPrefix ? words.slice(-1).join(' ') : words.slice(0, 1).join(' ');
    }

    $('body').on('mouseup', targetSelectors, function(e) {
        if (!seaco_vars.is_valid_page) return;
        
        if ($(e.target).is('input, textarea, select, button')) return;

        setTimeout(() => {
            let selection = window.getSelection();
            
            if (!selection || selection.isCollapsed || selection.toString().trim() === '') {
                $('#suggest-edits-tooltip').fadeOut(200);
                $('#suggest-edits-form-box').fadeOut(200);
                return;
            }

            let range = selection.getRangeAt(0);
            let startNode = range.startContainer;
            let endNode = range.endContainer;
            let startOffset = range.startOffset;
            let endOffset = range.endOffset;

            // Chỉ loại bỏ khoảng trắng (space, tab, enter) thừa ở hai đầu
            // KHÔNG loại bỏ các dấu câu hay ký tự đặc biệt
            if (startNode.nodeType === Node.TEXT_NODE) { 
                while (startOffset < startNode.nodeValue.length && startNode.nodeValue[startOffset].match(/\s/) && startOffset < endOffset) {
                    startOffset++;
                }
                range.setStart(startNode, startOffset);
            }

            if (endNode.nodeType === Node.TEXT_NODE) {
                while (endOffset > 0 && endNode.nodeValue[endOffset - 1].match(/\s/) && endOffset > startOffset) {
                    endOffset--;
                }
                range.setEnd(endNode, endOffset);
            }

            selection.removeAllRanges();
            selection.addRange(range);

            let text = selection.toString().trim();
            let prefix = '';
            let suffix = '';

            try {
                let contentNode = $(range.startContainer).closest(targetSelectors)[0];
                if (contentNode && contentNode.contains(range.startContainer) && contentNode.contains(range.endContainer)) {
                    prefix = getContextText(range.startContainer, range.startOffset, true);
                    suffix = getContextText(range.endContainer, range.endOffset, false);
                }
            } catch (err) {
                console.log("Context extraction failed:", err);
            }

            let maxLength = parseInt(seaco_vars.max_text_length, 10);
            if (isNaN(maxLength)) maxLength = 0;
            
            if (text.length > 0 && (maxLength === 0 || text.length <= maxLength)) {
                selectedText = text;
                selectedPrefix = prefix;
                selectedSuffix = suffix;

                const rects = range.getClientRects();
                
                if (rects.length > 0) {
                    const lastRect = rects[rects.length - 1];
                    let tooltipTop = lastRect.top + window.scrollY + 25; 
                    let tooltipLeft = lastRect.right + window.scrollX; 

                    if (tooltipLeft + 120 > $(window).width()) { 
                        tooltipLeft = lastRect.right + window.scrollX - 110;
                        tooltipTop = lastRect.bottom + window.scrollY + 5; 
                    }

                    $('#suggest-edits-tooltip').css({
                        top: tooltipTop + 'px',
                        left: tooltipLeft + 'px'
                    }).fadeIn(200);
                }

            } else {
                $('#suggest-edits-tooltip').fadeOut(200);
                $('#suggest-edits-form-box').fadeOut(200);
            }
        }, 10); 
    });

    $(window).on('scroll', function() {
        if ($('#suggest-edits-tooltip').is(':visible')) {
            $('#suggest-edits-tooltip').fadeOut(150);
        }
    });

    $('#suggest-edits-btn').on('click', function() {
        if (!seaco_vars.can_submit) {
            showToast(seaco_vars.i18n.perm_msg, true);
            return;
        }

        let tooltipPos = $('#suggest-edits-tooltip').position();
        $('#suggest-edits-tooltip').hide();
        
        $('#suggest-edits-form-box').css({
            top: tooltipPos.top + 'px',
            left: tooltipPos.left + 'px'
        }).fadeIn(200);
        $('#suggest-edits-content').focus();
    });

    $('#suggest-edits-cancel').on('click', function() {
        $('#suggest-edits-form-box').fadeOut(200);
        window.getSelection().removeAllRanges();
    });

    $('#suggest-edits-submit').on('click', function() {
        let suggest = $('#suggest-edits-content').val().trim();
        if (suggest === '') {
            showToast(seaco_vars.i18n.empty_se, true);
            return;
        }

        let $btn = $(this);
        $btn.text(seaco_vars.i18n.sending).prop('disabled', true);

        function sendSuggestAjax(recaptchaToken) {
            $.ajax({
                url: seaco_vars.ajax_url,
                type: 'POST',
                data: {
                    action: 'seaco_submit_feedback',
                    security: seaco_vars.nonce,
                    post_id: seaco_vars.post_id,
                    selected_text: selectedText, 
                    context_prefix: selectedPrefix, 
                    context_suffix: selectedSuffix, 
                    seaco_content: suggest,
                    recaptcha_token: recaptchaToken 
                },
                success: function(response) {
                    showToast(response.data, !response.success);
                    
                    if (response.success) {
                        $('#suggest-edits-form-box').fadeOut(200);
                        $('#suggest-edits-content').val('');
                        window.getSelection().removeAllRanges();
                    }
                },
                complete: function() {
                    $btn.text(seaco_vars.i18n.submit).prop('disabled', false);
                }
            });
        }

        if (seaco_vars.recaptcha_enable && typeof grecaptcha !== 'undefined') {
            grecaptcha.ready(function() {
                grecaptcha.execute(seaco_vars.recaptcha_site_key, {action: 'submit_feedback'}).then(function(token) {
                    sendSuggestAjax(token);
                }).catch(function() {
                    showToast(seaco_vars.i18n.rc_error, true);
                    $btn.text(seaco_vars.i18n.submit).prop('disabled', false);
                });
            });
        } else {
            sendSuggestAjax('');
        }
    });

    function refreshSuggestWidget() {
        if (document.hidden) return;

        let $widgetList = $('.suggest-edits-widget-list');
        if ($widgetList.length === 0) return; 

        let limit = $widgetList.data('limit') || 5;
        let textLimit = $widgetList.data('textlimit') || 50;
        let showTime = $widgetList.data('showtime') !== undefined ? $widgetList.data('showtime') : 1; 

        $.ajax({
            url: seaco_vars.ajax_url,
            type: 'POST',
            data: {
                action: 'seaco_refresh_widget',
                limit: limit,
                text_limit: textLimit,
                show_time: showTime
            },
            success: function(response) {
                if (response.success) {
                    $widgetList.html(response.data);
                }
            }
        });
    }
    setInterval(refreshSuggestWidget, 10000);

    const container = document.querySelector('.suggest-edits-widget-list');
    if(container) {
        let isScrolling;
        container.addEventListener('scroll', () => {
            container.classList.add('is-scrolling');
            window.clearTimeout(isScrolling);
            isScrolling = setTimeout(() => {
                container.classList.remove('is-scrolling');
            }, 1000);
        });
    }
});