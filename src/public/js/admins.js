function backToTop()
{
    var $backToTop = $('#back-to-top');
    $(window).scroll(function () {
        if ($(this).scrollTop() > 50) {
            $backToTop.fadeIn();
        } else {
            $backToTop.fadeOut();
        }
    });

    // scroll body to 0px on click
    $backToTop.click(function () {
        $backToTop.tooltip('hide');
        $('body,html').animate({
            scrollTop: 0
        }, 800);
        return false;
    });

    $backToTop.tooltip('show');
}

/**
 * Save JS data in LocalStorage
 */
function __storage(){
    var self = this;
    this.addData = function(name, code, expire){
        if(undefined !== Storage)
        {
            if(undefined === expire) expire = 3600000;
            else expire *= 1000;
            localStorage.setItem(name, JSON.stringify({data:code, time: new Date().getTime() + expire}));
        }
    };
    this.getData = function(name) {
        var data = null;
        if(undefined !== Storage)
        {
            var _data = JSON.parse(localStorage.getItem(name)),
                now = new Date().getTime();
            if(_data && (_data.time > now))
            {
                data = _data.data;
            }else this.dropData(name);
        }
        return data;
    };
    this.dropData = function(name)
    {
        if(undefined !== Storage)
        {
            localStorage.removeItem(name);
        }
    };
}
jsStorage = new __storage();

/**
 * Serialize form data into a JSON object
 * @returns {}
 */
$.fn.serializeObject = function()
{
    var o = {};
    var a = this.serializeArray();
    $.each(a, function() {
        if (o[this.name] !== undefined) {
            if (!o[this.name].push) {
                o[this.name] = [o[this.name]];
            }
            o[this.name].push(this.value || '');
        } else {
            o[this.name] = this.value || '';
        }
    });
    return o;
};

/**
 * Save the current form state
 * @param form
 */
function backupForm(form)
{
    var data = $(form).serializeObject(),
        name = $(form).attr("name");
    jsStorage.addData(name, data, 3600);
}

/**
 * Restore form values from backup
 * @param form
 */
function restoreForm(form)
{
    var $form = $(form),
        name = $form.attr("name"),
        data = jsStorage.getData(name);
    if(data)
    {
        for(var i in data)
        {
            $form.find(":input[name='" + i + "']").val(data[i]);
        }
        jsStorage.dropData(name);
    }
}

/**
 * Check whether data generators exist
 */
function checkCreationFields()
{
    var $forms = $("form");
    var w = 600;
    var h = 450;
    var left = Number((screen.width/2)-(w/2));
    var tops = Number((screen.height/2)-(h/2));
    $forms.each(function(){
        var $form = $(this);
        if($form.find("a[data-add]").length)
        {

            $form.find("a[data-add]").on("click", function(){
                _w = window.open($(this).attr("data-add"), '_blank', 'height='+h+',width='+w+',top='+tops+',left='+left);
                backupForm($form);
                $(_w).on("beforeunload", function(){
                    location.reload();
                });
            });
        }
    });
}

/**
 * Helper function for multi-select behavior
 * @param name
 * @param id
 * @param select
 * @returns {boolean}
 */
function changeMultiple(name, id, select, all)
{
    var $left = $("#src_" + id),
        $right = $("#dest_" + id),
        selected = (all) ? "" : ":selected";
    if(select)
    {
        $left.find("option" + selected).each(function(){
            var hidden = $("<input>");
            hidden.attr({
                "type": "hidden",
                "value": $(this).attr("value"),
                "name": name + "[]"
            });
            $right.append($(this).clone()).after(hidden);
            $(this).remove();
        });
    }else{
        $right.find("option" + selected).each(function(){
            var value = $(this).attr("value");
            $("input[type=hidden][name='"+name+"[]'][value="+value+"]").remove();
            $left.append($(this).clone());
            $(this).remove();
        });
    }
    return false;
}

function uploadImg(file)
{
    var url = $(file).attr("data-upload") || '';
    if(url.length > 0)
    {
        var formData = new FormData();
        //http://stackoverflow.com/questions/6974684/how-to-send-formdata-objects-with-ajax-requests-in-jquery
        formData.append('file', file.files[0]);

        $.ajax({
            url: url,
            data: formData,
            processData: false,
            contentType: false,
            type: 'POST',
            success: function(response){
                if(response.success)
                {
                    backupForm($(file).parents("form").get(0));
                    location.reload();
                }else{
                    bootbox.alert(response.error || 'Upload failed!');
                }
            }
        });

    }
}

function changeFocus(select, id)
{
    $("select[id*='"+id+"']").attr("data-focus", false);
    $(select).attr("data-focus", true);
}

function showDetails(id)
{
    var select = $("select[id*='"+id+"'][data-focus=true]"),
        text = '', sep = '', s = '';
    select.find("option").each(function(){
       if($(this).is(":selected"))
       {
           if(sep.length) s = "s";
           text += sep + $(this).text();
           sep = ", ";
       }
    });
    if(text.length)
    {
        bootbox.alert({
            title: "Selected option" + s,
            message: text
        });
    }
}

function toggleLogs(logGroup)
{
    $(".logs:not(.hide)").addClass("hide");
    $("." + logGroup).removeClass("hide");
}

function normalizeLauncherText(text)
{
    text = String(text || '').toLowerCase();
    if(typeof text.normalize === 'function') {
        text = text.normalize('NFD').replace(/[\u0300-\u036f]/g, '');
    }
    return text;
}

function launcherScore(query, item)
{
    var haystack = normalizeLauncherText([item.module, item.label, item.url].join(' '));
    if(query.length === 0) {
        return 1;
    }
    if(haystack.indexOf(query) !== -1) {
        return 4000 - haystack.indexOf(query);
    }

    var lastIndex = -1;
    var score = 0;
    for(var i = 0; i < query.length; i++) {
        var charIndex = haystack.indexOf(query.charAt(i), lastIndex + 1);
        if(charIndex === -1) {
            return -1;
        }
        score += Math.max(25 - (charIndex - lastIndex), 1);
        lastIndex = charIndex;
    }
    return score;
}

function prepareLauncherMenu(menu)
{
    var prepared = [],
        seen = {};
    if(!Array.isArray(menu)) {
        return prepared;
    }
    menu.forEach(function(item) {
        if(!item || !item.url || seen[item.url]) {
            return;
        }
        seen[item.url] = true;
        prepared.push({
            module: item.module || 'Admin',
            label: item.label || item.url,
            icon: item.icon || 'fa-link',
            url: item.url,
            hidden: item.hidden === true
        });
    });
    return prepared;
}

function createLauncherShell()
{
    if(document.getElementById('psfs-admin-launcher')) {
        return;
    }

    var style = document.createElement('style');
    style.id = 'psfs-admin-launcher-style';
    style.textContent = ''
        + '#psfs-admin-launcher{position:fixed;inset:0;z-index:3000;display:none;font-family:"Trebuchet MS","Helvetica Neue",sans-serif;}'
        + '#psfs-admin-launcher.is-open{display:block;}'
        + '#psfs-admin-launcher .psfs-launcher-backdrop{position:absolute;inset:0;background:radial-gradient(circle at top, rgba(255,255,255,0.18), rgba(10,18,35,0.88) 60%);backdrop-filter:blur(6px);}'
        + '#psfs-admin-launcher .psfs-launcher-panel{position:relative;max-width:760px;margin:10vh auto 0;background:linear-gradient(180deg,#ffffff 0%,#eef3f8 100%);border:1px solid rgba(15,23,42,0.18);border-radius:18px;box-shadow:0 24px 80px rgba(2,6,23,0.35);overflow:hidden;}'
        + '#psfs-admin-launcher .psfs-launcher-header{padding:20px 24px 14px;background:linear-gradient(135deg,#0f172a 0%,#1e3a5f 100%);color:#f8fafc;}'
        + '#psfs-admin-launcher .psfs-launcher-title{display:block;font-size:12px;letter-spacing:0.18em;text-transform:uppercase;opacity:0.72;margin-bottom:8px;}'
        + '#psfs-admin-launcher .psfs-launcher-input{width:100%;border:0;border-radius:12px;padding:14px 16px;font-size:18px;line-height:1.4;color:#0f172a;background:#f8fafc;box-shadow:inset 0 0 0 1px rgba(15,23,42,0.1);}'
        + '#psfs-admin-launcher .psfs-launcher-input:focus{outline:none;box-shadow:inset 0 0 0 2px rgba(37,99,235,0.55);}'
        + '#psfs-admin-launcher .psfs-launcher-list{max-height:420px;overflow:auto;padding:10px;}'
        + '#psfs-admin-launcher .psfs-launcher-item{display:flex;align-items:center;gap:14px;width:100%;padding:12px 14px;border:0;border-radius:12px;background:transparent;text-align:left;color:#0f172a;}'
        + '#psfs-admin-launcher .psfs-launcher-item:hover,#psfs-admin-launcher .psfs-launcher-item.is-active{background:#dbeafe;color:#0f172a;}'
        + '#psfs-admin-launcher .psfs-launcher-icon{width:34px;height:34px;border-radius:10px;display:flex;align-items:center;justify-content:center;background:#0f172a;color:#f8fafc;flex:0 0 34px;}'
        + '#psfs-admin-launcher .psfs-launcher-copy{display:block;}'
        + '#psfs-admin-launcher .psfs-launcher-meta{display:block;font-size:12px;letter-spacing:0.08em;text-transform:uppercase;color:#5b6b80;}'
        + '#psfs-admin-launcher .psfs-launcher-label{display:block;font-size:16px;font-weight:700;color:inherit;}'
        + '#psfs-admin-launcher .psfs-launcher-hidden{margin-left:auto;padding:4px 8px;border-radius:999px;background:#fff7ed;color:#9a3412;font-size:11px;font-weight:700;letter-spacing:0.08em;text-transform:uppercase;}'
        + '#psfs-admin-launcher .psfs-launcher-empty{padding:22px 18px;color:#475569;text-align:center;font-size:15px;}'
        + '#psfs-admin-launcher .psfs-launcher-hint{padding:12px 18px;border-top:1px solid rgba(15,23,42,0.08);font-size:12px;letter-spacing:0.06em;text-transform:uppercase;color:#475569;background:rgba(255,255,255,0.75);}'
        + '@media (max-width:768px){#psfs-admin-launcher .psfs-launcher-panel{margin:6vh 12px 0;}#psfs-admin-launcher .psfs-launcher-header{padding:18px 16px 12px;}#psfs-admin-launcher .psfs-launcher-input{font-size:16px;}}';
    document.head.appendChild(style);

    var shell = document.createElement('div');
    shell.id = 'psfs-admin-launcher';
    shell.setAttribute('aria-hidden', 'true');
    shell.innerHTML = ''
        + '<div class="psfs-launcher-backdrop" data-launcher-close="true"></div>'
        + '<div class="psfs-launcher-panel" role="dialog" aria-modal="true" aria-label="Admin launcher">'
        + '  <div class="psfs-launcher-header">'
        + '    <span class="psfs-launcher-title">Admin Launcher</span>'
        + '    <input id="psfs-launcher-query" name="psfs_launcher_query" class="psfs-launcher-input" type="search" autocomplete="off" spellcheck="false" placeholder="Search managers, tools, routes...">'
        + '  </div>'
        + '  <div class="psfs-launcher-list"></div>'
        + '  <div class="psfs-launcher-hint">Triple Left Shift to open. Use arrows, Enter or Esc.</div>'
        + '</div>';
    document.body.appendChild(shell);
}

function initAdminLauncher()
{
    var menu = prepareLauncherMenu(window.__psfsAdminMenu || []);
    if(!menu.length) {
        return;
    }

    createLauncherShell();

    var root = document.getElementById('psfs-admin-launcher'),
        input = root.querySelector('.psfs-launcher-input'),
        list = root.querySelector('.psfs-launcher-list'),
        isOpen = false,
        activeIndex = 0,
        results = [],
        shiftHits = [];

    function render()
    {
        var html = '';
        if(!results.length) {
            list.innerHTML = '<div class="psfs-launcher-empty">No matching admin routes. The menu remains unimpressed.</div>';
            return;
        }

        results.forEach(function(item, index) {
            html += ''
                + '<button type="button" class="psfs-launcher-item' + (index === activeIndex ? ' is-active' : '') + '" data-index="' + index + '">'
                +   '<span class="psfs-launcher-icon"><i class="fa ' + item.icon + '"></i></span>'
                +   '<span class="psfs-launcher-copy">'
                +     '<span class="psfs-launcher-meta">' + item.module + '</span>'
                +     '<span class="psfs-launcher-label">' + item.label + '</span>'
                +   '</span>'
                +   (item.hidden ? '<span class="psfs-launcher-hidden">Hidden</span>' : '')
                + '</button>';
        });
        list.innerHTML = html;
    }

    function refreshResults(query)
    {
        var normalizedQuery = normalizeLauncherText(query).replace(/\s+/g, ' ').trim();
        results = menu
            .map(function(item) {
                return {
                    item: item,
                    score: launcherScore(normalizedQuery, item)
                };
            })
            .filter(function(entry) {
                return entry.score >= 0;
            })
            .sort(function(a, b) {
                if(b.score !== a.score) {
                    return b.score - a.score;
                }
                return a.item.label.localeCompare(b.item.label);
            })
            .map(function(entry) {
                return entry.item;
            })
            .slice(0, 24);

        activeIndex = Math.min(activeIndex, Math.max(results.length - 1, 0));
        render();
    }

    function openLauncher()
    {
        isOpen = true;
        activeIndex = 0;
        root.classList.add('is-open');
        root.setAttribute('aria-hidden', 'false');
        input.value = '';
        refreshResults('');
        window.setTimeout(function() {
            input.focus();
            input.select();
        }, 10);
    }

    function closeLauncher()
    {
        isOpen = false;
        root.classList.remove('is-open');
        root.setAttribute('aria-hidden', 'true');
    }

    function moveSelection(step)
    {
        if(!results.length) {
            return;
        }
        activeIndex = (activeIndex + step + results.length) % results.length;
        render();
        var activeElement = list.querySelector('.psfs-launcher-item.is-active');
        if(activeElement && typeof activeElement.scrollIntoView === 'function') {
            activeElement.scrollIntoView({block: 'nearest'});
        }
    }

    function navigateToSelection()
    {
        if(results[activeIndex]) {
            window.location.href = results[activeIndex].url;
        }
    }

    input.addEventListener('input', function() {
        activeIndex = 0;
        refreshResults(input.value);
    });

    list.addEventListener('click', function(event) {
        var button = event.target.closest('.psfs-launcher-item');
        if(!button) {
            return;
        }
        activeIndex = parseInt(button.getAttribute('data-index'), 10) || 0;
        navigateToSelection();
    });

    root.addEventListener('click', function(event) {
        if(event.target && event.target.getAttribute('data-launcher-close') === 'true') {
            closeLauncher();
        }
    });

    document.addEventListener('keydown', function(event) {
        if(isOpen) {
            if(event.key === 'Escape') {
                event.preventDefault();
                closeLauncher();
                return;
            }
            if(event.key === 'ArrowDown') {
                event.preventDefault();
                moveSelection(1);
                return;
            }
            if(event.key === 'ArrowUp') {
                event.preventDefault();
                moveSelection(-1);
                return;
            }
            if(event.key === 'Enter') {
                event.preventDefault();
                navigateToSelection();
            }
            return;
        }

        if(event.repeat || event.code !== 'ShiftLeft') {
            if(event.key !== 'Shift') {
                shiftHits = [];
            }
            return;
        }

        var now = Date.now();
        shiftHits = shiftHits.filter(function(hit) {
            return now - hit < 700;
        });
        shiftHits.push(now);
        if(shiftHits.length >= 3) {
            shiftHits = [];
            event.preventDefault();
            openLauncher();
        }
    }, true);
}

(function(){
    backToTop();
    checkCreationFields();
    $("form").each(function(){
        restoreForm(this);
    });

    $('[data-switch-user]').on('click', function(event) {
        if (!window.confirm('Switch user and require new credentials?')) {
            event.preventDefault();
        }
    });

    $("[data-tooltip]").tooltip();
    initAdminLauncher();
})();
