<?php

/**
 * This file is part of the Sprog package.
 *
 * @author (c) Thomas Blum <thomas@addoff.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Sprog;

class Sync
{
    public static function articleNameToCategoryName($params)
    {
        try {
            $id = $params['id'];
            $clangId = $params['clang'];
            $parentId = isset($params['parent_id']) ? $params['parent_id'] : -1;
            $articleName = isset($params['name']) ? $params['name'] : '';

            if ($articleName != '') {
                \rex_sql::factory()
                    ->setTable(\rex::getTable('article'))
                    ->setWhere('(id = :id OR (parent_id = :parent_id AND startarticle = 0)) AND clang_id = :clang', ['id' => $id, 'parent_id' => $parentId, 'clang' => $clangId])
                    ->setValue('catname', $articleName)
                    ->addGlobalUpdateFields()
                    ->update();

                \rex_article_cache::delete($id, $clangId);
            }
        } catch (\rex_sql_exception $e) {
            throw new \rex_api_exception($e);
        }
    }

    public static function categoryNameToArticleName($params)
    {
        try {
            $id = $params['id'];
            $clangId = $params['clang'];
            $categoryName = isset($params['data']['catname']) ? $params['data']['catname'] : '';

            if ($categoryName != '') {
                \rex_sql::factory()
                    ->setTable(\rex::getTable('article'))
                    ->setWhere('id = :id AND clang_id = :clang', ['id' => $id, 'clang' => $clangId])
                    ->setValue('name', $categoryName)
                    ->addGlobalUpdateFields()
                    ->update();

                \rex_article_cache::delete($id, $clangId);
            }
        } catch (\rex_sql_exception $e) {
            throw new \rex_api_exception($e);
        }
    }

    public static function articleStatus($params)
    {
        try {
            $id = $params['id'];
            $clangId = $params['clang'];
            $status = $params['status'];

            // ----- Update Article Status
            \rex_sql::factory()
                ->setTable(\rex::getTable('article'))
                ->setWhere('id = :id AND clang_id != :clang', ['id' => $id, 'clang' => $clangId])
                ->setValue('status', $status)
                ->addGlobalUpdateFields()
                ->update();

            \rex_article_cache::delete($id);
        } catch (\rex_sql_exception $e) {
            throw new \rex_api_exception($e);
        }
    }

    public static function articleTemplate($params)
    {
        try {
            $id = $params['id'];
            $clangId = $params['clang'];
            $templateId = isset($params['template_id']) ? $params['template_id'] : 0;

            // ----- Update Template Id
            if ($templateId > 0) {
                \rex_sql::factory()
                    ->setTable(\rex::getTable('article'))
                    ->setWhere('id = :id AND clang_id != :clang', ['id' => $id, 'clang' => $clangId])
                    ->setValue('template_id', $templateId)
                    ->addGlobalUpdateFields()
                    ->update();

                \rex_article_cache::delete($id);
            }
        } catch (\rex_sql_exception $e) {
            throw new \rex_api_exception($e);
        }
    }

    public static function articleMetainfo($params, $fields, $toClangId = 0)
    {
        // Check whether field exists in table
        $sql = \rex_sql::factory()->setQuery('SELECT * FROM '.\rex::getTable('article').' LIMIT 1');
        $fieldNames = $sql->getFieldnames();
        foreach ($fields as $index => $field) {
            if (!in_array($field, $fieldNames)) {
                unset($fields[$index]);
            }
        }

        if (count($fields) < 1) {
            return;
        }


        $id = $params['id'];
        $clangId = $params['clang'];
        $saveFields = \rex_sql::factory()
            ->setTable(\rex::getTable('article'))
            ->setWhere('id = :id AND clang_id = :clang', ['id' => $id, 'clang' => $clangId])
            ->select(implode(',', $fields))
            ->getArray();

        if (count($saveFields) == 1) {
            $saveFields = $saveFields[0];
            try {
                // ----- Update Category Metainfo
                \rex_sql::factory()
                    ->setTable(\rex::getTable('article'))
                    ->setWhere('id = :id AND clang_id '.($toClangId > 0 ? '=' : '!=').' :clang', ['id' => $id, 'clang' => ($toClangId > 0 ? $toClangId : $clangId)])
                    ->setValues($saveFields)
                    ->addGlobalUpdateFields()
                    ->update();

                \rex_article_cache::delete($id);
            } catch (\rex_sql_exception $e) {
                throw new \rex_api_exception($e);
            }
        }
    }

    public static function categoryMetainfo($params, $fields)
    {
        self::articleMetainfo($params, $fields);
    }


    public static function ensureAddonWildcards(\rex_addon $addon)
    {
        $sql   = \rex_sql::factory();
        $isql  = \rex_sql::factory();
        $langs = \rex_clang::getAll(false);

        foreach ($langs as $lang) {
            $langId   = $lang->getId();
            $filepath = $addon->getPath("install/lang/{$lang->getCode()}.csv");

            if (file_exists($filepath)) {
                if (($handle = fopen($filepath, "r")) !== false) {
                    while (($row = fgetcsv($handle, 0, ";")) !== false) {
                        if (trim($row[0]) == '') {
                            continue;
                        }

                        $item = current($sql->getArray('SELECT pid, `replace` FROM rex_sprog_wildcard WHERE `wildcard` = :wc AND `clang_id` = :cid', [
                            'wc'  => $row[0],
                            'cid' => $langId,
                        ]));

                        if (!$item || trim($item['replace']) == '') {
                            $isql->setTable('rex_sprog_wildcard');
                            $isql->setValue('updatedate', date('Y-m-d H:i:s'));
                            $isql->setValue('updateuser', 'sprog-sync');
                            $isql->setValue('clang_id', $langId);
                            $isql->setValue('wildcard', trim($row[0]));
                            $isql->setValue('replace', $row[1]);

                            try {
                                if ($item) {
                                    $isql->setWhere('pid = :pid', ['pid' => $item['pid']]);
                                    $isql->update();
                                } else {
                                    $isql->setValue('createuser', 'sprog-sync');
                                    $isql->setValue('createdate', date('Y-m-d H:i:s'));
                                    $item = current($sql->getArray('SELECT id FROM rex_sprog_wildcard WHERE `wildcard` = :wc', [
                                        'wc' => $row[0],
                                    ]));

                                    if ($item) {
                                        $isql->setValue('id', $item['id']);
                                    } else {
                                        $_id = current($sql->getArray('SELECT (MAX(id) + 1) AS id FROM rex_sprog_wildcard'));
                                        $isql->setRawValue('id', $_id['id']);

                                        foreach ($langs as $_lang) {
                                            $_langId = $_lang->getId();
                                            $_isql   = clone $isql;

                                            if ($_langId != $langId) {
                                                $_isql->setValue('replace', '');
                                                $_isql->setValue('clang_id', $_langId);
                                                $_isql->insert();
                                            }
                                        }
                                    }
                                    $isql->insert();
                                }
                                if ($isql->hasError()) {
                                    throw new \rex_sql_exception($isql->getError(), null, $isql);
                                }
                            } catch (\rex_sql_exception $ex) {
                                pr($ex->getMessage(), 'red');
                                exit;
                            }
                        }
                    }
                    fclose($handle);
                }
            }
        }
    }
}
