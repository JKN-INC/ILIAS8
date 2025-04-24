<?php

declare(strict_types=0);

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
 * Class ilObjUserTrackingGUI
 * @author       Stefan Meyer <smeyer.ilias@gmx.de>
 * @ilCtrl_Calls ilLPListOfObjectsGUI: ilUserFilterGUI, ilTrUserObjectsPropsTableGUI, ilTrSummaryTableGUI, ilTrObjectUsersPropsTableGUI, ilTrMatrixTableGUI
 * @package      ilias-tracking
 */
class ilLPListOfObjectsGUI extends ilLearningProgressBaseGUI
{
    protected int $details_id = 0;
    protected int $details_obj_id = 0;
    protected string $details_type = '';
    protected int $details_mode = 0;

    public function __construct(int $a_mode, int $a_ref_id)
    {
        parent::__construct($a_mode, $a_ref_id);
        $this->__initDetails(
            $this->initDetailsIdFromRequest($this->getRefId())
        );
    }

    protected function initUserDetailsIdFromQuery(): int
    {
        if ($this->http->wrapper()->query()->has('userdetails_id')) {
            return $this->http->wrapper()->query()->retrieve(
                'userdetails_id',
                $this->refinery->kindlyTo()->int()
            );
        }
        return 0;
    }

    protected function initUserIdFromRequest(): int
    {
        if ($this->initUserIdFromQuery()) {
            return $this->initUserIdFromQuery();
        }
        if ($this->http->wrapper()->post()->has('user_id')) {
            return $this->http->wrapper()->post()->retrieve(
                'user_id',
                $this->refinery->kindlyTo()->int()
            );
        }
        return 0;
    }

    protected function initDetailsIdFromRequest(int $default_id): int
    {
        if ($this->http->wrapper()->query()->has('details_id')) {
            return $this->http->wrapper()->query()->retrieve(
                'details_id',
                $this->refinery->kindlyTo()->int()
            );
        }
        if ($this->http->wrapper()->post()->has('details_id')) {
            return $this->http->wrapper()->post()->retrieve(
                'details_id',
                $this->refinery->kindlyTo()->int()
            );
        }
        return $default_id;
    }

    public function executeCommand(): void
    {
        $this->ctrl->setReturn($this, "");

        switch ($this->ctrl->getNextClass()) {
            case 'iltruserobjectspropstablegui':
                $user_id = $this->initUserIdFromQuery();
                $this->ctrl->setParameter($this, "user_id", $user_id);

                $this->ctrl->setParameter(
                    $this,
                    "details_id",
                    $this->details_id
                );

                $table_gui = new ilTrUserObjectsPropsTableGUI(
                    $this,
                    "userDetails",
                    $user_id,
                    $this->details_obj_id,
                    $this->details_id
                );
                $this->ctrl->forwardCommand($table_gui);
                break;

            case 'iltrsummarytablegui':
                $cmd = "showObjectSummary";
                if (!$this->details_id) {
                    $this->details_id = ROOT_FOLDER_ID;
                    $cmd = "show";
                }
                $table_gui = new ilTrSummaryTableGUI(
                    $this,
                    $cmd,
                    $this->details_id
                );
                $this->ctrl->forwardCommand($table_gui);
                break;

            case 'iltrmatrixtablegui':
                $table_gui = new ilTrMatrixTableGUI(
                    $this,
                    "showUserObjectMatrix",
                    $this->details_id
                );
                $this->ctrl->forwardCommand($table_gui);
                break;

            case 'iltrobjectuserspropstablegui':
                $this->ctrl->setParameter(
                    $this,
                    "details_id",
                    $this->details_id
                );

                $table_gui = new ilTrObjectUsersPropsTableGUI(
                    $this,
                    "details",
                    $this->details_obj_id,
                    $this->details_id
                );
                $this->ctrl->forwardCommand($table_gui);
                break;

            default:
                $cmd = $this->__getDefaultCommand();
                $this->$cmd();
        }
    }

    public function updateUser()
    {
        $details_id = $this->initUserDetailsIdFromQuery();
        if ($details_id) {
            $parent = $this->details_id;
            $this->__initDetails($details_id);
        }

        if (!ilLearningProgressAccess::checkPermission(
            'edit_learning_progress',
            $this->details_id
        )) {
            $this->tpl->setOnScreenMessage(
                'failure',
                $this->lng->txt("permission_denied"),
                true
            );
            $this->ctrl->returnToParent($this);
        }

          //Rubric Functionality JKN.
          if ($this->details_mode == 92) {
            $passing_grade = $this->saveRubricGrade();
            if ($passing_grade !== false) {
                $this->__updateUserRubric(
                    $_REQUEST['user_id'],
                    $this->details_obj_id,
                    $passing_grade
                );
            }
        } else {
            $this->__updateUser($_REQUEST['user_id'], $this->details_obj_id);
            $this->tpl->setOnScreenMessage(
                'success',
                $this->lng->txt("trac_update_edit_user"),
                true
            );
        }

        $this->__updateUser(
            $this->initUserIdFromRequest(),
            $this->details_obj_id
        );
        $this->tpl->setOnScreenMessage(
            'success',
            $this->lng->txt('trac_update_edit_user'),
            true
        );

        $this->ctrl->setParameter(
            $this,
            "details_id",
            $this->details_id
        ); // #15043

        // #14993
        if (!$details_id) {
            $this->ctrl->redirect($this, "details");
        } else {
            $this->ctrl->setParameter($this, "userdetails_id", $details_id);
            $this->ctrl->redirect($this, "userdetails");
        }
    }

    public function editUser(): void
    {
        $cancel = '';
        $parent_id = $this->details_id;
        $details_id = $this->initUserDetailsIdFromQuery();
        if ($details_id) {
            $this->__initDetails($details_id);
            $sub_id = $this->details_id;
            $cancel = "userdetails";
        } else {
            $sub_id = null;
            $cancel = "details";
        }

        if (!ilLearningProgressAccess::checkPermission(
            'edit_learning_progress',
            $this->details_id
        )) {
            $this->tpl->setOnScreenMessage(
                'failure',
                $this->lng->txt("permission_denied"),
                true
            );
            $this->ctrl->returnToParent($this);
        }

        if ($this->details_mode === 92) {
            $this->showRubricGradeForm();
        } else {
            $info = new ilInfoScreenGUI($this);
            $info->setFormAction($this->ctrl->getFormAction($this));
            $this->__showObjectDetails($info, $this->details_obj_id);

            $user_id = $this->initUserIdFromQuery();
            $this->tpl->setVariable(
                "ADM_CONTENT",
                $this->__showEditUser(
                    $user_id,
                    $parent_id,
                    strlen($cancel) > 0 ? $cancel : null,
                    $sub_id ?? 0
                ) . "<br />" . $info->getHTML()
            );
        }

    }

    public function confirmRegrade(): void
    {
        $conf = new ilConfirmationGUI();
        $conf->setFormAction($this->ctrl->getFormAction($this));
        $conf->setHeaderText($this->lng->txt('rubric_regrade_warning'));
        $conf->setFormAction($this->ctrl->getFormAction($this));
        $conf->addHiddenItem('user_id', $_POST['user_id']);
        $conf->setConfirm($this->lng->txt('rubric_regrade'), 'regradeUser');
        $conf->setCancel($this->lng->txt('cancel'), 'cancelRegrade');
        $this->tpl->setContent($conf->getHTML());
    }

    function regradeUser()
    {
        $usr_id = $_POST['user_id'];
        $obj_id = ilObject::_lookupObjectId($_GET['ref_id']);
        include_once("./Services/Tracking/classes/rubric/class.ilLPRubricGrade.php");
        ilLPRubricGrade::_prepareForRegrade($obj_id, $usr_id);
        $obj_gui = new ilLPListOfObjectsGUI(0, $_GET['ref_id']);
        $obj_gui->editUser();;
    }

    function cancelRegrade(): void
    {
        //send back to the rubric
        $obj_gui = new ilLPListOfObjectsGUI(0, $_GET['ref_id']);
        $obj_gui->editUser();
    }

    public function exportGradedPdf(): void
    {
        include_once("./Services/Tracking/classes/rubric/class.ilRubricPDF.php");
        $rubricPDF = new ilRubricPDF($this->getObjId());
        $rubricPDF->exportGradedPDF();
    }

    public function exportPDF(): void
    {
        include_once("./Services/Tracking/classes/rubric/class.ilRubricPDF.php");
        $rubricPDF = new ilRubricPDF($this->getObjId());
        $rubricPDF->exportPDF();
    }

    /**
     *  Save Rubric Grade
     */
    private function saveRubricGrade(): mixed
    {
        // bring in the rubric card object
        include_once("./Services/Tracking/classes/rubric/class.ilLPRubricGrade.php");
        $rubricObj = new ilLPRubricGrade($this->getObjId());
        if ($rubricObj->objHasRubric()) {
            $rubricObj->grade($rubricObj->load());
            $this->tpl->setOnScreenMessage(
                'success',
                $this->lng->txt("rubric_card_save"),
                true
            );
            $rubricObj->sendRubricNotification($_REQUEST['user_id'], $this->details_obj_id);
        } else {
            $this->tpl->setOnScreenMessage(
                'failure',
                $this->lng->txt("rubric_card_not_defined"),
                true
            );
        }
        if ($rubricObj->isGradeCompleted()) {
            return ($rubricObj->getPassingGrade());
        } else {
            return (false);
        }
    }

    /**
     *  Show Rubric Grade
     */
    public function showRubricGradeForm($history_id = NULL): void
    {
        include_once('./Services/Tracking/classes/rubric/class.ilLPRubricGrade.php');
        include_once('./Services/Tracking/classes/rubric/class.ilLPRubricGradeGUI.php');
        $rubricObj = new ilLPRubricGrade($this->getObjId());
        $rubricGui = new ilLPRubricGradeGUI();
        $a_user = ilObjectFactory::getInstanceByObjId((int)$_REQUEST['user_id']);
        if ($rubricObj->objHasRubric() && $rubricObj->isRubricComplete()) {
            $rubricGui->setUserHistoryId($history_id);
            if ($rubricObj->isGradingLocked()) {
                $rubricGui->setRubricGradeLocked($rubricObj->getRubricGradeLocked());
                $rubricGui->setGradeLockOwner($rubricObj->getGradeLockOwner());
            }
            $rubricGui->setRubricData($rubricObj->load());
            $rubricGui->setUserHistory($rubricObj->getUserHistory((int)$_REQUEST['user_id']));
            $rubricGui->setUserData($rubricObj->getRubricUserGradeData((int)$_REQUEST['user_id'], $history_id));
            $rubricGui->setRubricComment($rubricObj->getRubricComment($_REQUEST['user_id'], $history_id));
            $rubricGui->getRubricGrade(
                $this->ctrl->getFormAction($this),
                $a_user->getFullName(),
                (int)$_REQUEST['user_id']
            );
        } else {
            if (!$rubricObj->objHasRubric()) {
                $this->tpl->setOnScreenMessage(
                    'failure',
                    $this->lng->txt("rubric_card_not_defined"),
                    true
                );
            } elseif (!$rubricObj->isRubricComplete()) {
                $this->tpl->setOnScreenMessage(
                    'failure',
                    $this->lng->txt('rubric_card_not_completed') . '<a href="' . $this->ctrl->getLinkTargetByClass('illplistofobjectsgui', 'showRubricCardForm')
                    . '">' . $this->lng->txt('rubric_card_please_complete') . '</a>',
                    true
                );
            }
        }
    }

    /**
     * Save Rubric Card
     */
    public function saveRubricCard(): void
    {
        // bring in the rubric card object
        include_once("./Services/Tracking/classes/rubric/class.ilLPRubricCard.php");
        $rubricObj = new ilLPRubricCard($this->getObjId());
        $rubricObj->save();
        $this->tpl->setOnScreenMessage(
            'success',
            $this->lng->txt("rubric_card_save"),
            true
        );
        include_once("./Services/Tracking/classes/rubric/class.ilLPRubricCardGUI.php");
        $rubricGui = new ilLPRubricCardGUI();
        if ($rubricObj->objHasRubric()) {
            $rubricGui->setRubricMode($rubricObj->_lookupRubricMode());
            $rubricGui->setRubricData($rubricObj->load());
        }
        $rubricGui->setPassingGrade($rubricObj->getPassingGrade());
        $rubricGui->getRubricCard($this->ctrl->getFormAction($this));
    }

    /**
     * Show Rubric Form
     */
    public function showRubricCardForm(): void
    {
        if ($this->isAnonymized()) {
            ilUtil::sendFailure($this->lng->txt('permission_denied'));
            return;
        }
        // bring in GUI and DB objects
        include_once("./Services/Tracking/classes/rubric/class.ilLPRubricCard.php");
        include_once("./Services/Tracking/classes/rubric/class.ilLPRubricCardGUI.php");
        // instantiate rubric objects
        $rubricGui = new ilLPRubricCardGUI();
        $rubricObj = new ilLPRubricCard($this->getObjId());
        // check to see if rubric data exists for this object, assign data if it does
        if ($rubricObj->objHasRubric()) {
            $rubricGui->setRubricData($rubricObj->load());
        }
        $rubricGui->setRubricMode($rubricObj->_lookupRubricMode());
        $rubricGui->setPassingGrade($rubricObj->getPassingGrade());
        if ($rubricObj->isLocked()) {
            $rubricGui->setRubricLocked($rubricObj->getRubricLocked());
            $rubricGui->setRubricOwner($rubricObj->getRubricOwner());
        }
        $rubricGui->getRubricCard($this->ctrl->getFormAction($this));
    }

    public function lockRubricCardForm()
    {
        include_once("./Services/Tracking/classes/rubric/class.ilLPRubricCardGUI.php");
        include_once("./Services/Tracking/classes/rubric/class.ilLPRubricCard.php");
        $rubricObj = new ilLPRubricCard($this->getObjId());
        $rubricObj->lockUnlock();
        if ($rubricObj->isLocked()) {
            $this->saveRubricCard();
        }
        $this->showRubricCardForm();
    }

    function viewHistory(): void
    {
        //send back to the rubric
        $obj_gui = new ilLPListOfObjectsGUI(0, $_GET['ref_id']);
        $obj_gui->showRubricGradeForm($_REQUEST['grader_history']);
    }

    // START PATCH JKN GRADEBOOK
    /**
     * Show Gradebook Weighting Form
     */
    public function showGradebookWeight(): void
    {
        global $tpl;
        include_once("./Services/Tracking/classes/gradebook/class.ilLPGradebookWeightGUI.php");
        include_once("./Services/Tracking/classes/gradebook/class.ilLPGradebookWeight.php");
        $gradebookObj = new ilLPGradebookWeight($this->getObjId());
        $gradebookGui = new ilLPGradebookWeightGUI();
        $gradebookGui->setVersions($gradebookObj->getGradebookVersions());
        $revision_id = $_POST['revision_id'] ?? null; // Check if 'revision_id' exists
        $course_structure = $gradebookObj->getInitialCourseStructure($this->getObjId(), $revision_id);
        $gradebookGui->setRevisionId($revision_id);
        $gradebookGui->setGradebookData($course_structure);
        $gradebookGui->view();
    }

    /**
     * Show Gradebook Grade By Student Form
     */
    public function showGradebookStudentGrade(): void
    {
        global $tpl;
        include_once("./Services/Tracking/classes/gradebook/class.ilLPGradebookGradeGUI.php");
        include_once("./Services/Tracking/classes/gradebook/class.ilLPGradebookGrade.php");
        $gradebookObj = new ilLPGradebookGrade($this->getObjId());
        $gradebookGui = new ilLPGradebookGradeGUI();
        $gradebookGui->setParticipants($gradebookObj->getCourseMembers());
        $gradebookGui->setVersions($gradebookObj->getGradebookVersions());
        $gradebookGui->view();
    }

    /**
     * Show Gradebook Course Participant
     */
    public function showGradebookCourseParticipants(): void
    {
        global $tpl;
        include_once("./Services/Tracking/classes/gradebook/class.ilLPGradebookGradeGUI.php");
        include_once("./Services/Tracking/classes/gradebook/class.ilLPGradebookGrade.php");
        $gradebookObj = new ilLPGradebookGrade($this->getObjId());
        $gradebookGui = new ilLPGradebookGradeGUI();
        $gradebookGui->setParticipantsData($gradebookObj->getCourseParticipantsData());
        $gradebookGui->courseParticipants();
    }
    // END PATCH JKN GRADEBOOK

    public function details(): void
    {
        global $DIC;

        $ilToolbar = $DIC['ilToolbar'];

        //START PATCH JKN GRADEBOOK
        if ($this->details_mode == 93) {
            $this->showGradebookCourseParticipants();
        } else {
            $this->tpl->addBlockFile('ADM_CONTENT', 'adm_content', 'tpl.lp_loo.html', 'Services/Tracking');

            $print_view = false;

            // Show back button
            if (
                $this->getMode() == self::LP_CONTEXT_PERSONAL_DESKTOP or
                $this->getMode() == self::LP_CONTEXT_ADMINISTRATION
            ) {
                $ilToolbar->addButton(
                    $this->lng->txt('trac_view_list'),
                    $this->ctrl->getLinkTarget($this, 'show')
                );
            }

            $info = new ilInfoScreenGUI($this);
            $info->setFormAction($this->ctrl->getFormAction($this));
            if ($this->__showObjectDetails($info, $this->details_obj_id)) {
                $this->tpl->setCurrentBlock("info");
                $this->tpl->setVariable("INFO_TABLE", $info->getHTML());
                $this->tpl->parseCurrentBlock();
            }

            $this->__showUsersList($print_view);
        }
        //END PATCH JKN GRADEBOOK
    }

    public function __showUsersList($a_print_view = false): void
    {
        if ($this->isAnonymized()) {
            $this->tpl->setOnScreenMessage(
                'failure',
                $this->lng->txt('permission_denied')
            );
            return;
        }
        $this->ctrl->setParameter($this, "details_id", $this->details_id);
        $gui = new ilTrObjectUsersPropsTableGUI(
            $this,
            "details",
            $this->details_obj_id,
            $this->details_id,
            $a_print_view
        );

        $this->tpl->setVariable("LP_OBJECTS", $gui->getHTML());
        $this->tpl->setVariable("LEGEND", $this->__getLegendHTML());
    }

    public function userDetails(): void
    {
        if ($this->isAnonymized()) {
            $this->tpl->setOnScreenMessage(
                'failure',
                $this->lng->txt('permission_denied')
            );
            return;
        }

        $this->ctrl->setParameter($this, "details_id", $this->details_id);

        $print_view = false;
        if ($this->http->wrapper()->query()->has('prt')) {
            $print_view = $this->http->wrapper()->query()->retrieve(
                'prt',
                $this->refinery->kindlyTo()->bool()
            );
        }
        if (!$print_view) {
            // Show back button
            $this->toolbar->addButton(
                $this->lng->txt('trac_view_list'),
                $this->ctrl->getLinkTarget(
                    $this,
                    'details'
                )
            );
        }

        $user_id = $this->initUserIdFromQuery();
        $this->ctrl->setParameter($this, "user_id", $user_id);
        $this->tpl->addBlockFile(
            'ADM_CONTENT',
            'adm_content',
            'tpl.lp_loo.html',
            'Services/Tracking'
        );

        $info = new ilInfoScreenGUI($this);
        $info->setFormAction($this->ctrl->getFormAction($this));
        $this->__showObjectDetails($info, $this->details_obj_id);
        // $this->__appendLPDetails($info,$this->details_obj_id,$user_id);
        $this->tpl->setVariable("INFO_TABLE", $info->getHTML());

        $table = new ilTrUserObjectsPropsTableGUI(
            $this,
            "userDetails",
            $user_id,
            $this->details_obj_id,
            $this->details_id,
            $print_view
        );
        $this->tpl->setVariable('LP_OBJECTS', $table->getHTML());
        $this->tpl->setVariable('LEGEND', $this->__getLegendHTML());
    }

    public function show(): void
    {
        $this->ctrl->setParameter($this, 'offset', 0);

        // Show only detail of current repository item if called from repository
        switch ($this->getMode()) {
            case self::LP_CONTEXT_REPOSITORY:
                $this->__initDetails($this->getRefId());
                $this->details();
                return;
        }
        $this->__listObjects();
    }

    public function __listObjects(): void
    {
        $this->tpl->addBlockFile(
            'ADM_CONTENT',
            'adm_content',
            'tpl.lp_list_objects.html',
            'Services/Tracking'
        );

        $lp_table = new ilTrSummaryTableGUI($this, "", ROOT_FOLDER_ID);

        $this->tpl->setVariable("LP_OBJECTS", $lp_table->getHTML());
        if ($lp_table->isStatusShown()) {
            $this->tpl->setVariable('LEGEND', $this->__getLegendHTML(ilLPStatusIcons::ICON_VARIANT_SHORT));
        }
    }

    public function __initDetails(int $a_details_id): void
    {
        if (!$a_details_id) {
            $a_details_id = $this->getRefId();
        }
        if ($a_details_id) {
            $this->details_id = $a_details_id;
            $this->details_obj_id = $this->ilObjectDataCache->lookupObjId(
                $this->details_id
            );
            $this->details_type = $this->ilObjectDataCache->lookupType(
                $this->details_obj_id
            );

            $olp = ilObjectLP::getInstance($this->details_obj_id);
            $this->details_mode = $olp->getCurrentMode();
        }
    }

    /**
     * Show object-based summarized tracking data
     */
    public function showObjectSummary(): void
    {
        $table = new ilTrSummaryTableGUI(
            $this,
            "showObjectSummary",
            $this->getRefId(),
            false
        );
        $content = $table->getHTML();
        if ($table->isStatusShown()) {
            $content .= $this->__getLegendHTML(ilLPStatusIcons::ICON_VARIANT_SHORT);
        }
        $this->tpl->setContent($content);
    }

    /**
     * Show object user matrix
     */
    public function showUserObjectMatrix(): void
    {
        if ($this->isAnonymized()) {
            $this->tpl->setOnScreenMessage(
                'failure',
                $this->lng->txt('permission_denied')
            );
            return;
        }
        $this->tpl->addBlockFile(
            'ADM_CONTENT',
            'adm_content',
            'tpl.lp_loo.html',
            'Services/Tracking'
        );
        $info = new ilInfoScreenGUI($this);
        $info->setFormAction($this->ctrl->getFormAction($this));
        if ($this->__showObjectDetails($info, $this->details_obj_id)) {
            $this->tpl->setCurrentBlock("info");
            $this->tpl->setVariable("INFO_TABLE", $info->getHTML());
            $this->tpl->parseCurrentBlock();
        }

        $table = new ilTrMatrixTableGUI(
            $this,
            "showUserObjectMatrix",
            $this->getRefId()
        );
        $this->tpl->setVariable('LP_OBJECTS', $table->getHTML());
        $this->tpl->setVariable('LEGEND', $this->__getLegendHTML());
    }
}
