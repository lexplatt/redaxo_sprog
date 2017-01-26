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

$func = rex_request('func', 'string');

if ($func == 'update')
{
    global $delimiter;

    $clangs    = [];
    $_values   = [];
    $sql       = \rex_sql::factory();
    $message   = $this->i18n('import_process') . "<br/><br/>";
    $force_ow  = rex_post('force_overwrite', 'int');
    $file_data = rex_files('csv-file');
    $__temp    = array_map('str_getcsv', file($file_data['tmp_name']));
    $delimiter = count($__temp[0]) == 1 ? ";" : ",";
    $handle    = fopen($file_data['tmp_name'], "r");

    while (($data = fgetcsv($handle, NULL, $delimiter)) !== FALSE)
    {
        $_values[] = $data;
    }
    fclose($handle);


    // find langs
    foreach (\rex_clang::getAll() as $lang)
    {
        $found = FALSE;
        foreach ($_values[0] as $index => $langCode)
        {
            if ($index == 0)
            {
                // bypass the wildcard column
                continue;
            }
            else if ($lang->getCode() == $langCode || strtolower($lang->getName()) == strtolower($langCode))
            {
                $found          = TRUE;
                $clangs[$index] = $lang->getId();
                $message .= '<p class="bg-success">' . $this->i18n('import_lang_importing', strtoupper($lang->getName())) . "</p>";
                break;
            }
        }
        if (!$found)
        {
            $message .= '<p class="bg-warning">' . $this->i18n('import_lang_not_exists', strtoupper($lang->getName())) . "</p>";
        }
    }
    unset($_values[0]);

    
    foreach ($_values as $value)
    {
        $wildcard = trim($value[0]);
        $newId    = 0;

        if ($wildcard == '')
        {
            // no wildcard set - skip row
            continue;
        }

        foreach ($clangs as $column => $clang_id)
        {
            $replace = trim($value[$column]);

            // check if wildcard already exists for this lang
            $sql->setQuery('SELECT * FROM ' . \rex::getTable('sprog_wildcard') . ' WHERE `wildcard` = :wildcard', [':wildcard' => $wildcard]);
            if ($sql->getRows() > 0)
            {
                // check if it exists for the given lang_id
                $rows = $sql->getArray();
                $sql->setTable(\rex::getTable('sprog_wildcard'));
                $sql->setValue('clang_id', $clang_id);
                $sql->setValue('wildcard', $wildcard);
                $sql->setValue('replace', $replace);
                $sql->addGlobalUpdateFields();
                $sql->addGlobalCreateFields();

                $found = FALSE;

                foreach ($rows as $row)
                {
                    $sql->setValue('id', $row['id']);
                    if ($row['clang_id'] == $clang_id)
                    {
                        if ($force_ow)
                        {
                            if ($replace == '')
                            {
                                // empty value - delete row
                                $sql->setWhere(['id' => $row['id'], 'clang_id' => $clang_id]);
                                $sql->delete();
                                $message .= $this->i18n('import_wildcard_deleted', $wildcard) . "<br/>";
                            }
                            else
                            {
                                // update the exiting row for the given lang
                                $sql->setWhere(['id' => $row['id'], 'clang_id' => $clang_id]);
                                $sql->update();
                                $message .= $this->i18n('import_wildcard_updated', $wildcard) . "<br/>";
                            }
                        }
                        $found = TRUE;
                        break;
                    }
                }
                if (!$found && strlen($replace))
                {
                    // is not present in db
                    $sql->insert();
                    $message .= $this->i18n('import_wildcard_added', $wildcard) . "<br/>";
                }
            }
            else if (strlen($replace))
            {
                $sql->setTable(\rex::getTable('sprog_wildcard'));
                if (!$newId)
                {
                    $newId = $sql->setNewId('id');
                }
                $sql->setValue('id', $newId);
                $sql->setValue('clang_id', $clang_id);
                $sql->setValue('wildcard', $wildcard);
                $sql->setValue('replace', $replace);
                $sql->addGlobalUpdateFields();
                $sql->addGlobalCreateFields();
                $sql->insert();

                $message .= $this->i18n('import_wildcard_added', $wildcard) . "<br/>";
            }
        }
    }
    
    echo \rex_view::success($message);
}

// - - - - - - - - - - - - - - - - - - - - - - Import
$panelElements = '';

// upload field
$formElements = [
    [
        'label' => '<label for="wildcard-open-tag">' . $this->i18n('import_file_label') . '</label>',
        'field' => '<input type="file" class="form-control text-right" name="csv-file" />',
    ],
];
$fragment     = new \rex_fragment();
$fragment->setVar('elements', $formElements, FALSE);
$panelElements .= $fragment->parse('core/form/form.php');

// overwrite checkbox
$formElements = [
    [
        'label' => '<label for="wildcard-clang-switch">' . $this->i18n('import_overwrite_wildcards') . '</label>',
        'field' => '<input type="checkbox" name="force_overwrite" value="1" />',
    ],
];
$fragment     = new \rex_fragment();
$fragment->setVar('elements', $formElements, FALSE);
$panelElements .= $fragment->parse('core/form/checkbox.php');


$panelBody = '
    <fieldset>
        <input type="hidden" name="func" value="update" />
        ' . $panelElements . '
    </fieldset>';
$fragment  = new \rex_fragment();
$fragment->setVar('class', 'edit', FALSE);
$fragment->setVar('title', $this->i18n('import_csv_upload'), FALSE);
$fragment->setVar('body', $panelBody, FALSE);
$sections .= $fragment->parse('core/page/section.php');


// - - - - - - - - - - - - - - - - - - - - - - Buttons

$formElements = [
    ['field' => '<button class="btn btn-apply rex-form-aligned" type="submit" name="send" value="1"' . \rex::getAccesskey(\rex_i18n::msg('sprog_upload'), 'apply') . '>' . \rex_i18n::msg('sprog_upload') . '</button>'],
];

$fragment = new \rex_fragment();
$fragment->setVar('elements', $formElements, FALSE);
$buttons = $fragment->parse('core/form/submit.php');


$fragment = new \rex_fragment();
$fragment->setVar('class', 'edit', FALSE);
$fragment->setVar('buttons', $buttons, FALSE);
$sections .= $fragment->parse('core/page/section.php');

echo '<form action="' . \rex_url::currentBackendPage() . '" method="post" enctype="multipart/form-data">' . $sections . '</form>';
