<?php

/** @noinspection PhpUnhandledExceptionInspection */

/**
 * SugarCRM Community Edition is a customer relationship management program developed by
 * SugarCRM, Inc. Copyright (C) 2004-2013 SugarCRM Inc.
 *
 * SuiteCRM is an extension to SugarCRM Community Edition developed by SalesAgility Ltd.
 * Copyright (C) 2011 - 2018 SalesAgility Ltd.
 *
 * This program is free software; you can redistribute it and/or modify it under
 * the terms of the GNU Affero General Public License version 3 as published by the
 * Free Software Foundation with the addition of the following permission added
 * to Section 15 as permitted in Section 7(a): FOR ANY PART OF THE COVERED WORK
 * IN WHICH THE COPYRIGHT IS OWNED BY SUGARCRM, SUGARCRM DISCLAIMS THE WARRANTY
 * OF NON INFRINGEMENT OF THIRD PARTY RIGHTS.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT
 * ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS
 * FOR A PARTICULAR PURPOSE. See the GNU Affero General Public License for more
 * details.
 *
 * You should have received a copy of the GNU Affero General Public License along with
 * this program; if not, see http://www.gnu.org/licenses or write to the Free
 * Software Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA
 * 02110-1301 USA.
 *
 * You can contact SugarCRM, Inc. headquarters at 10050 North Wolfe Road,
 * SW2-130, Cupertino, CA 95014, USA. or at email address contact@sugarcrm.com.
 *
 * The interactive user interfaces in modified source and object code versions
 * of this program must display Appropriate Legal Notices, as required under
 * Section 5 of the GNU Affero General Public License version 3.
 *
 * In accordance with Section 7(b) of the GNU Affero General Public License version 3,
 * these Appropriate Legal Notices must retain the display of the "Powered by
 * SugarCRM" logo and "Supercharged by SuiteCRM" logo. If the display of the logos is not
 * reasonably feasible for technical reasons, the Appropriate Legal Notices must
 * display the words "Powered by SugarCRM" and "Supercharged by SuiteCRM".
 */

/**
 * Created by PhpStorm.
 * User: viocolano
 * Date: 05/07/18
 * Time: 15:01
 */

use SuiteCRM\Search\Index\AbstractIndexer;
use SuiteCRM\Search\Index\Documentify\JsonSerializerDocumentifier;
use SuiteCRM\Search\Index\Documentify\SearchDefsDocumentifier;


class AbstractIndexerTest extends \SuiteCRM\Search\SearchTestAbstract
{
    public function testLog()
    {
        $indexer = new TestIndexer();
        $indexer->setEchoLogsEnabled(true);

        ob_start();
        self::invokeMethod($indexer, 'log', ['@', 'test notice']);
        $content = ob_get_flush();
        self::assertEquals($content, " [\033[32m@\033[0m] test notice\n");

        ob_start();
        self::invokeMethod($indexer, 'log', ['*', 'test warn']);
        $content = ob_get_flush();
        self::assertEquals($content, " [\033[33m*\033[0m] test warn\n");

        ob_start();
        self::invokeMethod($indexer, 'log', ['!', 'test error']);
        $content = ob_get_flush();
        self::assertEquals($content, " [\033[31m!\033[0m] test error\n");
    }

    public function testConstruct()
    {
        $indexer = new TestIndexer();
        self::assertInstanceOf(AbstractIndexer::class, $indexer);
    }

    public function testGettersAndSetters()
    {

        $output = false;
        $differential = true;
        $doc = new SearchDefsDocumentifier();
        $modules = ['Module1', 'Module2'];

        $i = new TestIndexer();

        $i->setEchoLogsEnabled($output);
        $i->setDifferentialIndexingEnabled($differential);
        $i->setDocumentifier($doc);
        $i->setModulesToIndex($modules);

        self::assertEquals($output, $i->isEchoLogsEnabled());
        self::assertEquals($differential, $i->isDifferentialIndexingEnabled());
        self::assertEquals($doc, $i->getDocumentifier());
        self::assertEquals($modules, $i->getModulesToIndex());

        $i = new TestIndexer();

        $output = true;
        $differential = false;
        $doc = new JsonSerializerDocumentifier();
        $modules = ['Foo', 'Bar'];

        $i->setEchoLogsEnabled($output);
        $i->setDifferentialIndexingEnabled($differential);
        $i->setDocumentifier($doc);
        $i->setModulesToIndex($modules);

        self::assertEquals($output, $i->isEchoLogsEnabled());
        self::assertEquals($differential, $i->isDifferentialIndexingEnabled());
        self::assertEquals($doc, $i->getDocumentifier());
        self::assertEquals($modules, $i->getModulesToIndex());
    }

    public function testAddModulesToIndex()
    {
        $i = new TestIndexer();
        $i->addModulesToIndex('Foo');

        self::assertContains('Foo', $i->getModulesToIndex());

        $i->addModulesToIndex(['Fu', 'Bar']);

        self::assertContains('Fu', $i->getModulesToIndex());
        self::assertContains('Bar', $i->getModulesToIndex());

        try {
            $i->addModulesToIndex((object)["test"]);
            self::fail("Exception should have been thrown");
        } catch (InvalidArgumentException $ignore) {
            // All good!
        } catch (Exception $e) {
            throw $e;
        }
    }

    public function testGetDocumentifierName()
    {
        $i = new TestIndexer();
        $doc1 = new JsonSerializerDocumentifier();
        $doc2 = new SearchDefsDocumentifier();
        $doc1Exp = 'JsonSerializerDocumentifier';
        $doc2Exp = 'SearchDefsDocumentifier';

        $i->setDocumentifier($doc1);
        self::assertEquals($doc1Exp, $i->getDocumentifierName());

        $i->setDocumentifier($doc2);
        self::assertEquals($doc2Exp, $i->getDocumentifierName());
    }

    public function testGetIndexerName()
    {
        $i = new TestIndexer();

        $expected = "TestIndexer";
        $actual = $i->getIndexerName();

        self::assertEquals($expected, $actual, "Indexer name does not match");
    }
}

class TestIndexer extends AbstractIndexer
{
    function run()
    {
    }

    function indexModule($module)
    {
    }

    function indexBean($bean)
    {
    }

    function indexBeans($module, $beans)
    {
    }

    function removeBean($bean)
    {
    }

    function removeBeans($bean)
    {
    }

    function removeIndex()
    {
    }
}