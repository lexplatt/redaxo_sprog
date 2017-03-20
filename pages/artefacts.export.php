<?php

/**
 * This file is part of the Sprog package.
 *
 * @author (c) Alex Platter <a.platter@kreatif.it>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Sprog;

$func = rex_request('func', 'string');

if ($func == 'export')
{
    $outtype = rex_get('output', 'string', 'file');

    // find the corresponding clangs
    $sql       = \rex_sql::factory();
    $clangs    = \rex_clang::getAll();
    $csv_head  = ['Wildcard'];
    $csv_body  = [];

    // get set lang header
    foreach ($clangs as $clang)
    {
        $csv_head[] = $clang->getValue('name');
    }

    // wildcards
    $query     = "SELECT `wildcard`, `replace`, `clang_id` FROM rex_sprog_wildcard ORDER BY `clang_id`, `wildcard`";
    $wildcards = $sql->getArray($query);

    foreach ($wildcards as $wildcard)
    {
        $csv_body[$wildcard['wildcard']][$wildcard['clang_id']] = $wildcard['replace'];
    }

    ob_clean();

    if ($outtype == 'file')
    {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment;filename=platzhalter-'. date('Ymd') .'.csv');
    }

    $out = fopen('php://output', 'w');

    fputcsv($out, $csv_head);

    foreach ($csv_body as $wildcard => $v)
    {
        array_unshift($v, $wildcard);
        fputcsv($out, $v);
    }
    fclose($out);
    exit;
}
else
{
    // - - - - - - - - - - - - - - - - - - - - - - Info

    $panelBody = '
        <p>' . \rex_i18n::rawMsg('sprog_csv_download_text') . '</p>
    ';
    $fragment  = new \rex_fragment();
    $fragment->setVar('title', $this->i18n('sprog_csv_download'), FALSE);
    $fragment->setVar('body', $panelBody, FALSE);
    echo $fragment->parse('core/page/section.php');


    // - - - - - - - - - - - - - - - - - - - - - - Buttons

    $formElements = [
        ['field' => '<a href="' . \rex_url::backendPage('sprog/artefacts/export', ['func' => 'export']) . '" class="btn btn-apply rex-form-aligned" ' . \rex::getAccesskey(\rex_i18n::msg('sprog_csv_download'), 'apply') . '>' . \rex_i18n::msg('sprog_csv_download') . '</a>'],
    ];

    $fragment = new \rex_fragment();
    $fragment->setVar('elements', $formElements, FALSE);
    $buttons = $fragment->parse('core/form/submit.php');


    $fragment = new \rex_fragment();
    $fragment->setVar('class', 'edit', FALSE);
    $fragment->setVar('buttons', $buttons, FALSE);
    echo $fragment->parse('core/page/section.php');
}

