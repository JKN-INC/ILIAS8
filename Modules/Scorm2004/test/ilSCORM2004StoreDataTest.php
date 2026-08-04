<?php

declare(strict_types=1);

/**
 * This file is part of ILIAS, a powerful learning management system
 * published by ILIAS open source e-Learning e.V.
 *
 * ILIAS is licensed with the GPL-3.0,
 * see https://www.gnu.org/licenses/gpl-3.0.en.html
 * You should have received a copy of said license along with the
 * source code, too.
 *
 * If this is not the case or you just want to try ILIAS, you'll find
 * us at:
 * https://www.ilias.de
 * https://github.com/ILIAS-eLearning
 *
 *********************************************************************/

/**
 * Regression tests for the SCORM 2004 "null now_global_status" bug: syncGlobalStatus()
 * used to require a non-nullable int $new_global_status, so a client payload with a
 * missing/null now_global_status (e.g. from the SCORM Offline Player, or a tab closed
 * right after Commit/Terminate) threw an uncaught TypeError under strict_types - after
 * cmi_node had already been persisted, but before sahs_user/ut_lp_marks were touched.
 *
 * @author Uwe Kohnle <support@internetlehrer-gmbh.de>
 */
class ilSCORM2004StoreDataTest extends ilScorm2004BaseTestCase
{
    private const PACKAGE_ID = 42;
    private const USER_ID = 6;
    private const REF_ID = 99;

    protected function setUp(): void
    {
        parent::setUp();

        if (!defined('ILIAS_LOG_ENABLED')) {
            define('ILIAS_LOG_ENABLED', false);
        }
        if (!defined('CLIENT_ID')) {
            define('CLIENT_ID', 1);
        }

        $this->addGlobal_ilDB();
        $this->addGlobal_ilObjDataCache();
        $this->addGlobal_ilAppEventHandler();

        // For a trustworthy status, syncGlobalStatus() calls ilLPStatus::writeStatus(),
        // which resolves its logger via $DIC->logger()->trac() - backed by
        // $DIC['ilLoggerFactory']. Stub it so that path doesn't fatal on a plain,
        // unconfigured mock (calling ->debug() on null).
        $componentLogger = $this->createMock(ilLogger::class);
        $loggerFactory = $this->createMock(ilLoggerFactory::class);
        $loggerFactory->method('getComponentLogger')->willReturn($componentLogger);
        $this->setGlobalVariable('ilLoggerFactory', $loggerFactory);
    }

    private function buildData(): stdClass
    {
        $data = new stdClass();
        $data->saved_global_status = 'incomplete';
        $data->totalTimeCentisec = 12345;
        $data->percentageCompleted = 0;
        return $data;
    }

    public function test_syncGlobalStatus_nullStatus_doesNotThrowAndSkipsStatusColumn(): void
    {
        $data = $this->buildData();

        /** @var ilDBInterface&PHPUnit\Framework\MockObject\MockObject $ilDB */
        $ilDB = $GLOBALS['DIC']['ilDB'];
        $ilDB->expects($this->once())
            ->method('queryF')
            ->with(
                $this->stringContains('UPDATE sahs_user SET sco_total_time_sec=%s, percentage_completed=%s'),
                array('integer', 'integer', 'integer', 'integer'),
                array(123, 0, self::PACKAGE_ID, self::USER_ID)
            );
        // no trustworthy status -> ilLPStatus::writeStatus() (and its ut_lp_marks upsert)
        // must never be reached
        $ilDB->expects($this->never())->method('replace');

        ilSCORM2004StoreData::syncGlobalStatus(
            self::USER_ID,
            self::PACKAGE_ID,
            self::REF_ID,
            $data,
            null,
            true
        );
    }

    public function test_syncGlobalStatus_validStatus_updatesStatusColumnAndWritesLearningProgress(): void
    {
        $data = $this->buildData();

        /** @var ilDBInterface&PHPUnit\Framework\MockObject\MockObject $ilDB */
        $ilDB = $GLOBALS['DIC']['ilDB'];
        $ilDB->expects($this->once())
            ->method('queryF')
            ->with(
                $this->stringContains('UPDATE sahs_user SET sco_total_time_sec=%s, status=%s, percentage_completed=%s'),
                array('integer', 'integer', 'integer', 'integer', 'integer'),
                array(123, ilLPStatus::LP_STATUS_COMPLETED_NUM, 0, self::PACKAGE_ID, self::USER_ID)
            );
        // ilLPStatus::writeStatus() takes the insert path (no existing ut_lp_marks row in
        // the mock) - its replace() call into ut_lp_marks is our proxy for "learning
        // progress was actually written", since writeStatus() isn't independently mockable
        $ilDB->expects($this->atLeastOnce())->method('replace');

        ilSCORM2004StoreData::syncGlobalStatus(
            self::USER_ID,
            self::PACKAGE_ID,
            self::REF_ID,
            $data,
            ilLPStatus::LP_STATUS_COMPLETED_NUM,
            true
        );
    }

    public function test_syncGlobalStatus_notAttemptedStatusZero_isNotTreatedAsNull(): void
    {
        $data = $this->buildData();

        /** @var ilDBInterface&PHPUnit\Framework\MockObject\MockObject $ilDB */
        $ilDB = $GLOBALS['DIC']['ilDB'];
        $ilDB->expects($this->once())
            ->method('queryF')
            ->with(
                $this->stringContains('UPDATE sahs_user SET sco_total_time_sec=%s, status=%s, percentage_completed=%s'),
                array('integer', 'integer', 'integer', 'integer', 'integer'),
                array(123, ilLPStatus::LP_STATUS_NOT_ATTEMPTED_NUM, 0, self::PACKAGE_ID, self::USER_ID)
            );
        // under the old `!= null` check, an int 0 status would have been loosely-equal to
        // null and incorrectly skipped ilLPStatus::writeStatus() entirely
        $ilDB->expects($this->atLeastOnce())->method('replace');

        ilSCORM2004StoreData::syncGlobalStatus(
            self::USER_ID,
            self::PACKAGE_ID,
            self::REF_ID,
            $data,
            ilLPStatus::LP_STATUS_NOT_ATTEMPTED_NUM,
            true
        );
    }
}
