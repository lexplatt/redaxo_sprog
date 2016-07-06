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

$sections = '';
$message  = '';

$func = rex_request('func', 'string');

if ($func == 'find')
{
    // - - - - - - - - - - - - - - - - - - - - - - Find
    $wildcards = Wildcard::getUnusedWildcards();

    $panelBody = '
        <h3>' . sprintf($this->i18n('cleanup_unused_count'), count($wildcards)) . '</h3>
        <div>
            <a class="select-all" href="#">'. $this->i18n('cleanup_select_all') .'</a> |
            <a class="unselect-all" href="#">'. $this->i18n('cleanup_unselect_all') .'</a>
        </div><br/>
        <ul class="list-unstyled">
    ';

    foreach ($wildcards as $wc) {
        $panelBody .= '
            <li>
                <label>
                    <input type="checkbox" name="wildcards[]" value="'. $wc .'" /> '. $wc .'
                </label>
            </li>
        ';
    }
    $panelBody .= '</ul>';
    $panelBody .= '<button class="btn btn-apply" type="submit" name="func" value="delete">' . $this->i18n('cleanup_remove_selected') . '</button>';

    $fragment = new \rex_fragment();
    $fragment->setVar('title', $this->i18n('cleanup_find_title'), false);
    $fragment->setVar('class', 'edit', false);
    $fragment->setVar('body', $panelBody, false);
    $sections .= $fragment->parse('core/page/section.php');
}

else if ($func == 'delete')
{
    // - - - - - - - - - - - - - - - - - - - - - - Delete
    $wildcards = rex_post('wildcards', 'array');
    $sql       = \rex_sql::factory();
    $query     = 'DELETE FROM '. \rex::getTable('sprog_wildcard') .' WHERE wildcard IN("'. implode('","', $wildcards) .'")';
    $sql->setQuery($query);

    $message = \rex_view::info(sprintf($this->i18n('cleanup_items_removed'), count($wildcards)));
    $func    = '';
}

if ($func == '')
{
    // - - - - - - - - - - - - - - - - - - - - - - Info

    $panelBody = '
        <h3>' . $this->i18n('cleanup_title') . '</h3>
        <p>' . \rex_i18n::rawMsg('sprog_cleanup_info') . '</p>
    ';
    $fragment = new \rex_fragment();
    $fragment->setVar('title', $this->i18n('cleanup'), false);
    $fragment->setVar('body', $panelBody, false);
    $sections .= $fragment->parse('core/page/section.php');

    // - - - - - - - - - - - - - - - - - - - - - - Buttons

    $formElements = [
        ['field' => '<button class="btn btn-apply" type="submit" name="func" value="find"' . \rex::getAccesskey($this->i18n('cleanup_find_unused'), 'apply') . '>' . $this->i18n('cleanup_find_unused') . '</button>'],
    ];
    $fragment = new \rex_fragment();
    $fragment->setVar('elements', $formElements, false);
    $buttons = $fragment->parse('core/form/submit.php');

    $fragment = new \rex_fragment();
    $fragment->setVar('class', 'edit', false);
    $fragment->setVar('buttons', $buttons, false);
    $sections .= $fragment->parse('core/page/section.php');
}



echo $message;
echo '<form action="' . \rex_url::currentBackendPage() . '" method="post" enctype="multipart/form-data">' . $sections . '</form>';
