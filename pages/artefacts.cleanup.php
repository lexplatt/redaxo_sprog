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
    $open_tag         = Wildcard::getOpenTag();
    $close_tag        = Wildcard::getCloseTag();
    $unused_wildcards = Wildcard::getUnusedWildcards();

    $panelBody = '
        <div>
            <a class="select-all" href="#">' . $this->i18n('cleanup_select_all') . '</a> |
            <a class="unselect-all" href="#">' . $this->i18n('cleanup_unselect_all') . '</a>
        </div><br/>
        <ul class="list-unstyled">
    ';

    if (count($unused_wildcards))
    {
        // check if unused wilcards are used in fragments
        $wc_pattern = $open_tag .'[a-z].[a-z][^#]*'. $close_tag;
        exec("grep -rho -e '{$wc_pattern}' ". \rex_path::src('addons') ." | uniq", $found_file_usage);

        foreach ($unused_wildcards as $index => $wc)
        {
            if (in_array ($open_tag . $wc . $close_tag, $found_file_usage))
            {
                unset($unused_wildcards[$index]);
            }
        }
    }

    if (count($unused_wildcards))
    {
        // check if unused wilcards are used in fragments - find by static method call
        $wc_pattern = 'Wildcard::get(\W[a-z].[a-z][^)]*';
        exec("grep -rhoe '{$wc_pattern}' ". \rex_path::src('addons') ." | uniq | sed 's/Wildcard::get(\W//' | sed \"s/[']//\" | sed 's/[\"]//'", $found_file_usage);

        foreach ($unused_wildcards as $index => $wc)
        {
            if (in_array ($wc, $found_file_usage))
            {
                unset($unused_wildcards[$index]);
            }
        }
    }

    if (count($unused_wildcards))
    {
        // check if unused wilcards are used in developer files - find by static method call
        $wc_pattern = 'Wildcard::get(\W[a-z].[a-z][^)]*';
        exec("grep -rhoe '{$wc_pattern}' ". \rex_path::addonData('developer') ." | uniq | sed 's/Wildcard::get(\W//' | sed \"s/[']//\" | sed 's/[\"]//'", $found_file_usage);

        foreach ($unused_wildcards as $index => $wc)
        {
            if (in_array ($wc, $found_file_usage))
            {
                unset($unused_wildcards[$index]);
            }
        }
    }

    if (count($unused_wildcards))
    {
        // check if unused wilcards are used in foundation email
        $wc_pattern = $open_tag .'[a-z].[a-z][^#]*'. $close_tag;
        exec("grep -rho -e '{$wc_pattern}' ". \rex_path::addonData('foundation-email-templates/fragments') ." | uniq", $found_file_usage);

        foreach ($unused_wildcards as $index => $wc)
        {
            if (in_array ($open_tag . $wc . $close_tag, $found_file_usage))
            {
                unset($unused_wildcards[$index]);
            }
        }
    }

    if (count($unused_wildcards))
    {
        // check if unused wilcards are used in foundation email files - find by static method call
        $wc_pattern = 'Wildcard::get(\W[a-z].[a-z][^)]*';
        exec("grep -rhoe '{$wc_pattern}' ". \rex_path::addonData('foundation-email-templates/fragments') ." | uniq | sed 's/Wildcard::get(\W//' | sed \"s/[']//\" | sed 's/[\"]//'", $found_file_usage);

        foreach ($unused_wildcards as $index => $wc)
        {
            if (in_array ($wc, $found_file_usage))
            {
                unset($unused_wildcards[$index]);
            }
        }
    }

    if (count($unused_wildcards))
    {
        // check if unused wilcards are used in other wildcards
        foreach ($unused_wildcards as $index => $wc)
        {
            $sql = \rex_sql::factory();
            $sql->setQuery("SELECT wildcard FROM rex_sprog_wildcard WHERE `replace` LIKE :w1", [':w1' => "%{$open_tag}{$wc}{$close_tag}%"]);
            $found_wc_usage = $sql->getRow();

            if ($found_wc_usage)
            {
                unset($unused_wildcards[$index]);
            }
            else
            {
                $panelBody .= '
                    <li>
                        <label>
                            <input type="checkbox" name="wildcards[]" value="' . $wc . '" /> ' . $wc . '
                        </label>
                    </li>
                ';
            }
        }
    }


    $panelBody .= '</ul>';
    $panelBody .= '<button class="btn btn-apply" type="submit" name="func" value="delete">' . $this->i18n('cleanup_remove_selected') . '</button>';

    $fragment = new \rex_fragment();
    $fragment->setVar('title', $this->i18n('cleanup_find_title'), FALSE);
    $fragment->setVar('class', 'edit', FALSE);
    $fragment->setVar('body', '<h3>'. sprintf($this->i18n('cleanup_unused_count'), count($unused_wildcards)) .'</h3>'. $panelBody, FALSE);
    $sections .= $fragment->parse('core/page/section.php');
}

else if ($func == 'delete')
{
    // - - - - - - - - - - - - - - - - - - - - - - Delete
    $wildcards = rex_post('wildcards', 'array');
    $sql       = \rex_sql::factory();
    $query     = 'DELETE FROM ' . \rex::getTable('sprog_wildcard') . ' WHERE wildcard IN("' . implode('","', $wildcards) . '")';
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
    $fragment  = new \rex_fragment();
    $fragment->setVar('title', $this->i18n('cleanup'), FALSE);
    $fragment->setVar('body', $panelBody, FALSE);
    $sections .= $fragment->parse('core/page/section.php');

    // - - - - - - - - - - - - - - - - - - - - - - Buttons

    $formElements = [
        ['field' => '<button class="btn btn-apply" type="submit" name="func" value="find"' . \rex::getAccesskey($this->i18n('cleanup_find_unused'), 'apply') . '>' . $this->i18n('cleanup_find_unused') . '</button>'],
    ];
    $fragment     = new \rex_fragment();
    $fragment->setVar('elements', $formElements, FALSE);
    $buttons = $fragment->parse('core/form/submit.php');

    $fragment = new \rex_fragment();
    $fragment->setVar('class', 'edit', FALSE);
    $fragment->setVar('buttons', $buttons, FALSE);
    $sections .= $fragment->parse('core/page/section.php');
}


echo $message;
echo '<form action="' . \rex_url::currentBackendPage() . '" method="post" enctype="multipart/form-data">' . $sections . '</form>';
