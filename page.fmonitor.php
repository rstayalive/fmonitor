<?php
namespace FreePBX\modules;
if (!defined('FREEPBX_IS_AUTH')) { die('No direct script access allowed'); }

include_once('functions.inc.php');

$freepbx = \FreePBX::create();
$astman = $freepbx->astman;

$allExts = getAllExtensions();
$statuses = getExtensionStatuses($astman);
$queues = getQueuesStatus($astman);

echo '<div class="container-fluid">';
echo '<h1><i class="fa fa-dashboard"></i> FMonitor — Мониторинг в реальном времени</h1>';

// Избранные
echo '<div class="panel panel-primary">';
echo '<div class="panel-heading"><strong>⭐ Избранные номера</strong></div>';
echo '<div class="panel-body" id="favorites-panel">';
echo '<div class="row" id="fav-row"></div>';
echo '</div></div>';

// Вкладки
echo '<ul class="nav nav-tabs" id="fmonitorTabs">
    <li><a data-toggle="tab" href="#tab-extensions">Все сотрудники</a></li>
    <li class="active"><a data-toggle="tab" href="#tab-queues">Очереди</a></li>
</ul>';

echo '<div class="tab-content mt-4">';

// Все сотрудники
echo '<div id="tab-extensions" class="tab-pane">';
echo '<table class="table table-striped table-hover" id="ext-table">';
echo '<thead><tr><th style="width:60px">Избранное</th><th>Номер</th><th>Имя</th><th>Статус</th></tr></thead><tbody>';

foreach ($allExts as $e) {
    $ext = $e['extension'];
    $name = htmlspecialchars($e['name'] ?: '—');
    $st = $statuses[$ext] ?? ['text'=>'Неизвестно', 'color'=>'gray'];
    $colorClass = $st['color'] === 'green' ? 'success' : ($st['color'] === 'orange' ? 'warning' : ($st['color'] === 'blue' ? 'info' : 'danger'));

    echo "<tr data-ext='$ext'>
        <td><button class='btn btn-xs btn-star' data-ext='$ext'>☆</button></td>
        <td><strong>$ext</strong></td>
        <td>$name</td>
        <td><span class='label label-$colorClass ext-status'>{$st['text']}</span></td>
    </tr>";
}
echo '</tbody></table></div>';

// Очереди
echo '<div id="tab-queues" class="tab-pane active">';
echo '<h3>Очереди <small class="pull-right">Автообновление каждые 8 сек</small></h3>';

foreach ($queues as $qdata) {
    $q = $qdata['queue'];
    $waiting = $qdata['waiting'];

    echo "<div class='panel panel-info'>";
    echo "<div class='panel-heading'><strong>Очередь $q</strong> — <b>$waiting</b> звонков в ожидании</div>";
    echo "<div class='panel-body'>";

    if (!empty($qdata['members'])) {
        echo "<h5>Агенты в очереди</h5>";
        echo "<table class='table table-sm'>";
        echo "<tr><th>Номер</th><th>Статус</th><th>Принял звонков</th></tr>";
        foreach ($qdata['members'] as $m) {
            $c = $m['status']['color'];
            $badge = $c === 'green' ? 'success' : ($c === 'orange' ? 'warning' : ($c === 'blue' ? 'info' : 'danger'));
            echo "<tr><td>{$m['ext']}</td><td><span class='label label-$badge'>{$m['status']['text']}</span></td><td>{$m['calls_taken']}</td></tr>";
        }
        echo "</table>";
    }

    if (!empty($qdata['callers'])) {
        echo "<h5>Звонки в ожидании</h5>";
        echo "<table class='table table-sm'>";
        echo "<tr><th>Позиция</th><th>Звонок на DID</th><th>Ожидание</th></tr>";
        foreach ($qdata['callers'] as $c) {
            echo "<tr><td>{$c['position']}</td><td><strong>{$c['callerid']}</strong></td><td>{$c['wait']} сек</td></tr>";
        }
        echo "</table>";
    }
    echo '</div></div>';
}
echo '</div>';

echo '</div></div>';

echo <<< 'JS'
<script>
$(function() {
    let favorites = JSON.parse(localStorage.getItem('fmonitor_fav') || '[]');
    let savedTab = localStorage.getItem('fmonitor_tab') || '#tab-queues';
    $('.nav-tabs a[href="' + savedTab + '"]').tab('show');

    $('.nav-tabs a').on('shown.bs.tab', function(e) {
        localStorage.setItem('fmonitor_tab', e.target.hash);
    });

    function renderFavorites() {
        let html = '';
        let hasAny = false;
        $('#ext-table tbody tr').each(function() {
            let ext = $(this).data('ext');
            if (favorites.includes(String(ext))) {
                hasAny = true;
                let name = $(this).find('td:eq(2)').text().trim();
                let statusHtml = $(this).find('.ext-status').prop('outerHTML') || '<span class="label label-default">—</span>';
                html += `<div class="col-md-3 col-sm-4 col-xs-6 mb-3">
                    <div class="panel panel-default">
                        <div class="panel-body text-center">
                            <h3 class="mb-1">${ext}</h3>
                            <p class="text-muted mb-2">${name}</p>
                            ${statusHtml}
                        </div>
                    </div>
                </div>`;
            }
        });
        if (!hasAny) html = '<p class="text-muted">Добавьте номера в избранное, нажав ★ напротив нужного сотрудника.</p>';
        $('#fav-row').html(html);
    }

    $('.btn-star').on('click', function() {
        let ext = $(this).data('ext');
        if (favorites.includes(String(ext))) {
            favorites = favorites.filter(e => e !== String(ext));
            $(this).html('☆').removeClass('text-warning');
        } else {
            favorites.push(String(ext));
            $(this).html('★').addClass('text-warning');
        }
        localStorage.setItem('fmonitor_fav', JSON.stringify(favorites));
        renderFavorites();
    });

    $('.btn-star').each(function() {
        let ext = $(this).data('ext');
        if (favorites.includes(String(ext))) $(this).html('★').addClass('text-warning');
    });

    renderFavorites();
    setInterval(() => location.reload(), 8000);
});
</script>
JS;
?>