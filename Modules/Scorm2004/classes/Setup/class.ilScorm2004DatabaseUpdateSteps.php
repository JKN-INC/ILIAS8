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
 * Ports ilScorm2004DatabaseUpdateSteps::step_1 through step_5 from later, upstream
 * ILIAS versions (this component has no Setup/DatabaseUpdateSteps class yet on our
 * release_8 base, so none of these have run here before).
 *
 * step_1-3 widen cmi_interaction.c_timestamp/id and cmi_correct_response.pattern.
 * All three were originally sized for short, machine-generated SCORM identifiers,
 * but real-world content (e.g. multi-select "select all that apply" interactions,
 * whose correct-response pattern concatenates every correct choice with "[,]") can
 * legitimately exceed the old limits. When that happens the insert throws an
 * uncaught SQLSTATE[22001] "Data too long for column" error, which - since
 * ilSCORM2004StoreData::setCMIData() runs outside any transaction and isn't
 * wrapped in a try/catch - aborts the whole commit after cmi_node has already been
 * saved, leaving sahs_user/ut_lp_marks stuck without ever reaching the status sync.
 * This is the fix for the "correct_response.pattern too long" bug found while
 * testing the null-now_global_status fix on course ref_id 322.
 *
 * step_4 widens cp_dependency.resourceid for the same reason (same class of bug,
 * not yet observed here, but the column is equally undersized).
 *
 * step_5 adds a standalone index on sahs_user.user_id - the table's primary key is
 * the composite (obj_id, user_id), which can't be used efficiently for queries that
 * filter by user_id alone across objects; a performance fix, not a correctness one.
 */
class ilScorm2004DatabaseUpdateSteps implements ilDatabaseUpdateSteps
{
    protected ilDBInterface $db;

    public function prepare(ilDBInterface $db): void
    {
        $this->db = $db;
    }

    public function step_1(): void
    {
        $this->db->modifyTableColumn("cmi_interaction", "c_timestamp", array("type" => "text", "length" => 40, "notnull" => false, 'default' => null));
    }

    public function step_2(): void
    {
        $this->db->modifyTableColumn("cmi_correct_response", "pattern", array("type" => "text", "length" => 4000, "notnull" => false, 'default' => null));
    }

    public function step_3(): void
    {
        $this->db->modifyTableColumn("cmi_interaction", "id", array("type" => "text", "length" => 4000, "notnull" => false, 'default' => null));
    }

    public function step_4(): void
    {
        $this->db->modifyTableColumn("cp_dependency", "resourceid", array("type" => "text", "length" => 200, "notnull" => false, 'default' => null));
    }

    public function step_5(): void
    {
        if (!$this->db->indexExistsByFields('sahs_user', ['user_id'])) {
            $this->db->addIndex('sahs_user', ['user_id'], 'i1');
        }
    }
}
