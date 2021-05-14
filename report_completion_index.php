<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Course completion progress report
 *
 * @package    report
 * @subpackage completion
 * @copyright  2009 Catalyst IT Ltd
 * @author     Aaron Barnes <aaronb@catalyst.net.nz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once(__DIR__.'/../../../config.php');
require_once("{$CFG->libdir}/completionlib.php");

/**
 * Configuration
 */
define('COMPLETION_REPORT_PAGE',        25);
define('COMPLETION_REPORT_COL_TITLES',  true);

/*
 * Setup page, check permissions
 */

// Get course
$courseid = required_param('course', PARAM_INT);
$format = optional_param('format','',PARAM_ALPHA);
$sort = optional_param('sort','',PARAM_ALPHA);
$edituser = optional_param('edituser', 0, PARAM_INT);

$quizUL=183;


$course = $DB->get_record('course', array('id' => $courseid), '*', MUST_EXIST);
$context = context_course::instance($course->id);

$url = new moodle_url('/phlcohort/report/completion/index.php', array('course'=>$course->id));
$PAGE->set_url($url);
$PAGE->set_pagelayout('report');

$firstnamesort = ($sort == 'firstname');
$excel = ($format == 'excelcsv');
$csv = ($format == 'csv' || $excel);

$isMOF=false;
if(in_array($course->id,$CFG->mofids))
    $isMOF=true;
    
    // Load CSV library
    if ($csv) {
        require_once("{$CFG->libdir}/csvlib.class.php");
    }
    
    
    
    // Paging
    $start   = optional_param('start', 0, PARAM_INT);
    $sifirst = optional_param('sifirst', 'all', PARAM_NOTAGS);
    $silast  = optional_param('silast', 'all', PARAM_NOTAGS);
    
    // Whether to show extra user identity information
    $extrafields = get_extra_user_fields($context);
    $leftcols = 1 + count($extrafields);
    
    // Check permissions
    require_login($course);
    
    require_capability('report/completion:view', $context);
    
    // Get group mode
    $group = groups_get_course_group($course, true); // Supposed to verify group
    if ($group === 0 && $course->groupmode == SEPARATEGROUPS) {
        require_capability('moodle/site:accessallgroups',$context);
    }
    //echo $CFG->mofexams[$course->id][0].$CFG->mofexams[$course->id][1].in_array($course->id,$CFG->mofids);
    /*Custom code*/
    $savereport = optional_param('sreport', "", PARAM_TEXT);
    if($savereport==="true")
    {
        
        $checkByadmin=optional_param('chk', 0, PARAM_INT);
        $txtComment=optional_param('txt', "", PARAM_TEXT);
        $userid=optional_param('userid', 0, PARAM_INT);
        
        
        $report = $DB->get_records("phl_report", array('courseid' => $courseid,'ruserid' => $userid));
        
        $data = new stdClass();
        $data ->ruserid=$userid;
        $data ->courseid=$courseid;
        $data ->approved=$checkByadmin;
        $data ->status='';
        $data ->comment=$txtComment;
        
        if($report==null)
        {
            $DB->insert_record('phl_report', $data,false);
        }
        else
        {
            var_dump($report);
            foreach($report as $r)
            {
                $data->id=$r->id;
                $DB->update_record('phl_report', $data,false);
            }
        }
        
        
    }
    /*End Custom code*/
    
    /**
     * Load data
     */
    
    // Retrieve course_module data for all modules in the course
    $modinfo = get_fast_modinfo($course);
    
    // Get criteria for course
    $completion = new completion_info($course);
    
    if (!$completion->has_criteria()) {
        print_error('nocriteriaset', 'completion', $CFG->wwwroot.'/course/report.php?id='.$course->id);
    }
    
    // Get criteria and put in correct order
    $criteria = array();
    
    foreach ($completion->get_criteria(COMPLETION_CRITERIA_TYPE_COURSE) as $criterion) {
        $criteria[] = $criterion;
    }
    
    foreach ($completion->get_criteria(COMPLETION_CRITERIA_TYPE_ACTIVITY) as $criterion) {
        $criteria[] = $criterion;
    }
    
    foreach ($completion->get_criteria() as $criterion) {
        if (!in_array($criterion->criteriatype, array(
            COMPLETION_CRITERIA_TYPE_COURSE, COMPLETION_CRITERIA_TYPE_ACTIVITY))) {
                $criteria[] = $criterion;
            }
    }
    
    // Can logged in user mark users as complete?
    // (if the logged in user has a role defined in the role criteria)
    $allow_marking = false;
    $allow_marking_criteria = null;
    
    if (!$csv) {
        // Get role criteria
        $rcriteria = $completion->get_criteria(COMPLETION_CRITERIA_TYPE_ROLE);
        
        if (!empty($rcriteria)) {
            
            foreach ($rcriteria as $rcriterion) {
                $users = get_role_users($rcriterion->role, $context, true);
                
                // If logged in user has this role, allow marking complete
                if ($users && in_array($USER->id, array_keys($users))) {
                    $allow_marking = true;
                    $allow_marking_criteria = $rcriterion->id;
                    break;
                }
            }
        }
    }
    
    /*
     * Setup page header
     */
    if ($csv) {
        
        $shortname = format_string($course->shortname, true, array('context' => $context));
        $shortname = preg_replace('/[^a-z0-9-]/', '_',core_text::strtolower(strip_tags($shortname)));
        
        $export = new csv_export_writer();
        
        
    } else {
        // Navigation and header
        $strcompletion = get_string('coursecompletion');
        
        $PAGE->set_title($strcompletion);
        $PAGE->set_heading($course->fullname);
        
        echo $OUTPUT->header();
        
        // Handle groups (if enabled)
        groups_print_course_menu($course, $CFG->wwwroot.'/phlcohort/report/completion/index.php?course='.$course->id);
    }
    
    if ($sifirst !== 'all') {
        set_user_preference('ifirst', $sifirst);
    }
    if ($silast !== 'all') {
        set_user_preference('ilast', $silast);
    }
    
    if (!empty($USER->preference['ifirst'])) {
        $sifirst = $USER->preference['ifirst'];
    } else {
        $sifirst = 'all';
    }
    
    if (!empty($USER->preference['ilast'])) {
        $silast = $USER->preference['ilast'];
    } else {
        $silast = 'all';
    }
    
    // Generate where clause
    $where = array();
    $where_params = array();
    
    //Custom code
    $cohortid=optional_param('cohortid', 0, PARAM_INT);
    $ngay=optional_param('ngay', 0, PARAM_INT);
    $thang=optional_param('thang', 0, PARAM_INT);
    $nam=optional_param('nam', 0, PARAM_INT);
    $export->set_filename('PSS-'.$cohortid.'-'.$csv);
    $fullname=optional_param('fullname', '', PARAM_TEXT);
    $tungay=optional_param('tungay', '', PARAM_TEXT);
    $denngay=optional_param('denngay', '', PARAM_TEXT);
    
    $listULUsers=null;
    
    $gradeToPass=0;
    if($isMOF)
    {
        $CFG->mofexams=array("$courseid" => array(305,319));
        $pass = $CFG->mofexams[$course->id][0];
        $sql = "select * from mdl232x0_grade_items where courseid = $courseid and iteminstance = $pass";
        $get_grade = $DB->get_record_sql($sql,array());
        $gradeToPass=$get_grade->gradepass;
        if($cohortid>0 || $ngay >0 || $thang>0 || $nam>0)
        {
            $listUserIDs=GetUserByCohortID($cohortid,$ngay,$thang,$nam);
            $where[]='u.id IN ('.$listUserIDs.')';
            
        }
    }
    else
    {
        $gradeToPass=21;
        $listUserIDs=GetIDULUsers($quizUL,$listULUsers,$fullName,$tungay,$denngay,$gradeToPass);
        // echo $listUserIDs;
        $where[]='u.id IN ('.$listUserIDs.')';
        
    }
    //End custom code
    
    if ($sifirst !== 'all') {
        $where[] = $DB->sql_like('u.firstname', ':sifirst', false);
        $where_params['sifirst'] = $sifirst.'%';
    }
    if ($silast !== 'all') {
        $where[] = $DB->sql_like('u.lastname', ':silast', false);
        $where_params['silast'] = $silast.'%';
    }
    
    /*
     if ($sifirst !== 'all') {
     $where[] = $DB->sql_like('u.firstname', ':sifirst', false);
     $where_params['sifirst'] = $sifirst.'%';
     }
     if ($silast !== 'all') {
     $where[] = $DB->sql_like('u.lastname', ':silast', false);
     $where_params['silast'] = $silast.'%';
     }
     */
    //	var_dump($where);
    // Get user match count
    
    $total = $completion->get_num_tracked_users(implode(' AND ', $where), $where_params, $group);
    
    // Total user count
    $grandtotal = $completion->get_num_tracked_users('', array(), $group);
    
    // If no users in this course what-so-ever
    if (!$grandtotal) {
        echo $OUTPUT->container(get_string('err_nousers', 'completion'), 'errorbox errorboxcontent');
        echo $OUTPUT->footer();
        exit;
    }
    
    // Get user data
    $progress = array();
    
    if ($total) {
        $progress = $completion->get_progress_all(
            implode(' AND ', $where),
            $where_params,
            $group,
            $firstnamesort ? 'u.firstname ASC' : 'u.lastname ASC',
            $csv ? 0 : COMPLETION_REPORT_PAGE,
            $csv ? 0 : $start,
            $context
            );
    }
    
    // Build link for paging
    $link = $CFG->wwwroot.'/phlcohort/report/completion/index.php?course='.$course->id;
    if (strlen($sort)) {
        $link .= '&amp;sort='.$sort;
    }
    $link .= '&amp;start=';
    
    if($isMOF)
        $pagingbar = '<div>'.GetCohortList($cohortid,$ngay,$thang,$nam,$courseid).'</div>';
        else
        {
            $pagingbar = '<div>'.SearchControls($courseid,$fullname,$tungay,$denngay).'</div>';
        }
        
        // Initials bar.
        $prefixfirst = 'sifirst';
        $prefixlast = 'silast';
        //$pagingbar .= $OUTPUT->initials_bar($sifirst, 'firstinitial', get_string('firstname'), $prefixfirst, $url);
        //$pagingbar .= $OUTPUT->initials_bar($silast, 'lastinitial', get_string('lastname'), $prefixlast, $url);
        $pagingbar .= $OUTPUT->initials_bar($sifirst, 'firstinitial',"Họ", $prefixfirst, $url);
        $pagingbar .= $OUTPUT->initials_bar($silast, 'lastinitial', "Tên", $prefixlast, $url);
        
        // Do we need a paging bar?
        if ($total > COMPLETION_REPORT_PAGE) {
            
            // Paging bar
            $pagingbar .= '<div class="paging">';
            $pagingbar .= get_string('page').': ';
            
            $sistrings = array();
            if ($sifirst != 'all') {
                $sistrings[] =  "sifirst={$sifirst}";
            }
            if ($silast != 'all') {
                $sistrings[] =  "silast={$silast}";
            }
            $sistring = !empty($sistrings) ? '&amp;'.implode('&amp;', $sistrings) : '';
            
            // Display previous link
            if ($start > 0) {
                $pstart = max($start - COMPLETION_REPORT_PAGE, 0);
                $pagingbar .= "(<a class=\"previous\" href=\"{$link}{$pstart}{$sistring}\">".get_string('previous').'</a>)&nbsp;';
            }
            
            // Create page links
            $curstart = 0;
            $curpage = 0;
            while ($curstart < $total) {
                $curpage++;
                
                if ($curstart == $start) {
                    $pagingbar .= '&nbsp;'.$curpage.'&nbsp;';
                }
                else {
                    $pagingbar .= "&nbsp;<a href=\"{$link}{$curstart}{$sistring}\">$curpage</a>&nbsp;";
                }
                
                $curstart += COMPLETION_REPORT_PAGE;
            }
            
            // Display next link
            $nstart = $start + COMPLETION_REPORT_PAGE;
            if ($nstart < $total) {
                $pagingbar .= "&nbsp;(<a class=\"next\" href=\"{$link}{$nstart}{$sistring}\">".get_string('next').'</a>)';
            }
            
            $pagingbar .= '</div>';
        }
        
        /*
         * Draw table header
         */
        
        // Start of table
        if (!$csv) {
            print '<br class="clearer"/>'; // ugh
            
            $total_header = ($total == $grandtotal) ? $total : "{$total}/{$grandtotal}";
            echo $OUTPUT->heading(get_string('allparticipants').": {$total_header}", 3);
            
            print $pagingbar;
            
            if (!$total) {
                echo $OUTPUT->heading(get_string('nothingtodisplay'), 2);
                echo $OUTPUT->footer();
                exit;
            }
            
            print '<table id="completion-progress" class="table table-bordered generaltable flexible boxaligncenter
        completionreport" style="text-align: left" cellpadding="5" border="1">';
            
            // Print criteria group names
            print PHP_EOL.'<thead>';
            
            // Print user heading and icons
            print '<tr>';
            
            // User heading / sort option
            print '<th scope="col" class="completion-sortchoice" style="clear: both;">';
            
            $sistring = "&amp;silast={$silast}&amp;sifirst={$sifirst}";
            
            if ($firstnamesort) {
                print
                get_string('firstname')." / <a href=\"./index.php?course={$course->id}{$sistring}\">".
                    get_string('lastname').'</a>';
            } else {
                print "<a href=\"./index.php?course={$course->id}&amp;sort=firstname{$sistring}\">".
                    get_string('firstname').'</a> / '.
                    get_string('lastname');
            }
            print '</th>';
            
            // Print user identity columns
            foreach ($extrafields as $field) {
                echo '<th scope="col" class="completion-identifyfield">' .
                    get_user_field_name($field) . '</th>';
            }
            
            ///
            /// Print criteria icons
            ///
            foreach ($criteria as $criterion) {
                
                // Generate icon details
                $iconlink = '';
                $iconalt = ''; // Required
                $iconattributes = array('class' => 'icon');
                $customdisplay='';
                switch ($criterion->criteriatype) {
                    
                    case COMPLETION_CRITERIA_TYPE_ACTIVITY:
                        
                        // Display icon
                        $iconlink = $CFG->wwwroot.'/mod/'.$criterion->module.'/view.php?id='.$criterion->moduleinstance;
                        $iconattributes['title'] = $modinfo->cms[$criterion->moduleinstance]->get_formatted_name();
                        $iconalt = get_string('modulename', $criterion->module);
                        $customdisplay='customdisplay';
                        break;
                        
                    case COMPLETION_CRITERIA_TYPE_COURSE:
                        $customdisplay='customdisplay';
                        // Load course
                        $crs = $DB->get_record('course', array('id' => $criterion->courseinstance));
                        
                        // Display icon
                        $iconlink = $CFG->wwwroot.'/course/view.php?id='.$criterion->courseinstance;
                        $iconattributes['title'] = format_string($crs->fullname, true, array('context' => context_course::instance($crs->id, MUST_EXIST)));
                        $iconalt = format_string($crs->shortname, true, array('context' => context_course::instance($crs->id)));
                        break;
                        
                    case COMPLETION_CRITERIA_TYPE_ROLE:
                        $customdisplay='customdisplay';
                        // Load role
                        $role = $DB->get_record('role', array('id' => $criterion->role));
                        
                        // Display icon
                        $iconalt = $role->name;
                        break;
                }
                
                // Create icon alt if not supplied
                if (!$iconalt) {
                    $iconalt = $criterion->get_title();
                }
                
                // Print icon and cell
                print '<th class="criteriaicon'.' '.$customdisplay.'">';
                
                print ($iconlink ? '<a href="'.$iconlink.'" title="'.$iconattributes['title'].'">' : '');
                print $OUTPUT->render($criterion->get_icon($iconalt, $iconattributes));
                print ($iconlink ? '</a>' : '');
                
                print '</th>';
            }
            
            // Overall course completion status
            //print '<th class="criteriaicon">';
            //print $OUTPUT->pix_icon('i/course', get_string('coursecomplete', 'completion'));
            //print '</th>';
            echo '<th scope="col" class="completion-identifyfield">Lớp</th>';
            echo '<th scope="col" class="completion-identifyfield">Trạng thái</th>';
            echo '<th scope="col" class="completion-identifyfield">Kết quả</th>';
            if($isMOF)
            {
                echo '<th scope="col" class="completion-identifyfield">Điểm thi Cơ Bản</th>';
                echo '<th scope="col" class="completion-identifyfield">Điểm thi PHDP</th>';
                echo '<th scope="col" class="completion-identifyfield">Xem xét</th>';
                echo '<th scope="col" class="completion-identifyfield">Ghi chú</th>';
                echo '<th scope="col" class="completion-identifyfield"></th>';
            }
            else
            {
                echo '<th scope="col" class="completion-identifyfield">Điểm kiểm tra</th>';
                echo '<th scope="col" class="completion-identifyfield">Ngày</th>';
            }
            
            print '</tr></thead>';
            
            echo '<tbody>';
        } else {
            // The CSV headers
            $row = array();
            
            $row[] = "id";
            $row[] = "fullname";
            foreach ($extrafields as $field) {
                ;//$row[] = get_user_field_name($field);Email
            }
            
            // Add activity headers
            foreach ($criteria as $criterion) {
                
                // Handle activity completion differently
                if ($criterion->criteriatype == COMPLETION_CRITERIA_TYPE_ACTIVITY) {
                    
                    // Load activity
                    $mod = $criterion->get_mod_instance();
                    //$row[] = $formattedname = format_string($mod->name, true,
                    //array('context' => context_module::instance($criterion->moduleinstance)));
                    //$row[] = $formattedname . ' - ' . get_string('completiondate', 'report_completion');
                }
                else {
                    // Handle all other criteria
                    //$row[] = strip_tags($criterion->get_title_detailed());
                }
            }
            $row[] = "ngaysinh";
            $row[] = "thangsinh";
            $row[] = "namsinh";
            $row[] = "gioitinh";
            $row[] = "sodienthoai";
            if($isMOF)
            {
                $row[] = "cmtnd";
                $row[] = "ngaycap";
                $row[] = "noicap";
                $row[] = "malop";
                $row[] = "Điểm số A";
            }
            else{
                
                $row[] = "Điểm kiểm tra";
                $row[] = "Ngày";
            }
            $row[] = "Kết quả A";
            $row[] = "Điểm số B";
            $row[] = "Kết quả B";
            //$row[] = get_string('coursecomplete', 'completion');
            
            $export->add_data($row);
        }
        
        ///
        /// Display a row for each user
        ///
        foreach ($progress as $user) {
            
            // User name
            if ($csv) {
                $row = array();
                $row[] = $user->id;
                $row[] = fullname($user);
                foreach ($extrafields as $field) {
                    ;//$row[] = $user->{$field};Email
                }
            } else {
                print PHP_EOL.'<tr id="user-'.$user->id.'">';
                
                if (completion_can_view_data($user->id, $course)) {
                    $userurl = new moodle_url('/blocks/completionstatus/details.php', array('course' => $course->id, 'user' => $user->id));
                } else {
                    $userurl = new moodle_url('/user/view.php', array('id' => $user->id, 'course' => $course->id));
                }
                
                print '<th scope="row"><a href="'.$userurl->out().'">'.fullname($user).'</a></th>';
                foreach ($extrafields as $field) {
                    echo '<td>'.s($user->{$field}).'</td>';
                }
                //var_dump($extrafields);
            }
            
            // Progress for each course completion criteria
            //$isnot_complete=0;
            $is_completeAct=0;
            $totalActivity=0;
            foreach ($criteria as $criterion) {
                continue;
                $criteria_completion = $completion->get_user_completion($user->id, $criterion);
                $is_complete = $criteria_completion->is_complete();
                
                
                // Handle activity completion differently
                if ($criterion->criteriatype == COMPLETION_CRITERIA_TYPE_ACTIVITY) {
                    
                    $totalActivity++;
                    if($is_complete){
                        $is_completeAct++;
                        //var_dump($criteria_completion);
                    }
                    
                    
                    if ($csv) {
                        ;
                        //$row[] = $describe;
                        //$row[] = $date;
                    } else {
                        print '<td class="completion-progresscell customdisplay">';
                        
                        //print $OUTPUT->pix_icon('i/' . $completionicon, $fulldescribe);
                        
                        print '</td>';
                    }
                    
                    continue;
                }
                
                // Handle all other criteria
                $completiontype = $is_complete ? 'y' : 'n';
                $completionicon = 'completion-auto-'.$completiontype;
                
                $describe = get_string('completion-'.$completiontype, 'completion');
                
                $a = new stdClass();
                $a->state    = $describe;
                
                if ($is_complete) {
                    $a->date = userdate($criteria_completion->timecompleted, get_string('strftimedatetimeshort', 'langconfig'));
                } else {
                    $a->date = '';
                }
                
                $a->user     = fullname($user);
                $a->activity = strip_tags($criterion->get_title());
                $fulldescribe = get_string('progress-title', 'completion', $a);
                
                
            }
            
            // Handle overall course completion
            
            // Load course completion
            $params = array(
                'userid'    => $user->id,
                'course'    => $course->id
            );
            
            $ccompletion = new completion_completion($params);
            $completiontype =  $ccompletion->is_complete() ? 'y' : 'n';
            
            $describe = get_string('completion-'.$completiontype, 'completion');
            
            $a = new StdClass;
            
            if ($ccompletion->is_complete()) {
                $a->date = userdate($ccompletion->timecompleted, get_string('strftimedatetimeshort', 'langconfig'));
            } else {
                $a->date = '';
            }
            
            $a->state    = $describe;
            $a->user     = fullname($user);
            $a->activity = strip_tags(get_string('coursecomplete', 'completion'));
            $fulldescribe = get_string('progress-title', 'completion', $a);
            
            
            
            $cohort=GetCohorByUserID($user->id);
            $addedtime=GetCohorAddedTimeByUserID($user->id);
            $status='';
            $result='';
            $comment='';
            $app=0;
            
            $newSubmitDate=null;
            if($isMOF)
            {
                try{
                    $report =$DB->get_records("phl_report", array('courseid' => $course->id,'ruserid' => $user->id));
                    
                    foreach($report as $r)
                    {
                        $comment=$r->comment;
                        $app=$r->approved;
                    }
                    $report==null;
                } catch (Exception $e) {
                    $report==null;
                }
                
                
                
                $basicMark_A=GetQuizGradeByUserAndQuizID($user->id,$CFG->mofexams[$course->id][0],$submitDate);
                $basicMark_B=GetQuizGradeByUserAndQuizID($user->id,$CFG->mofexams[$course->id][1],$submitDate);
                $finalMark=GetQuizGradeByUserAndQuizID($user->id,$CFG->mofexams[$course->id][1],$submitDate);
                if($submitDate!=null)
                {
                    $date = date_create();
                    date_timestamp_set($date, $submitDate);
                    $newSubmitDate= date_format($date, 'm-d-Y H:i:s');
                    
                    
                }
                if($basicMark_B<$gradeToPass){
                    $status_B="Chưa hoàn thành";
                    $result_B="Không đạt";
                }
                else if($basicMark_B>=$gradeToPass)
                {
                    $status_B="Đã hoàn thành";
                    $result_B="Đạt";
                }
                if($basicMark_A<$gradeToPass){
                    $status_A="Chưa hoàn thành";
                    $result_A="Không đạt";
                }
                else if($basicMark_A>=$gradeToPass){
                    $status_A="Đã hoàn thành";
                    $result_A="Đạt";
                }
                //       if($basicMark<$gradeToPass || $finalMark <$gradeToPass){
                //          $status="Chưa hoàn thành";
                //           $result="Không đạt";
                //      }
                //      else if($basicMark>=$gradeToPass && $finalMark >=$gradeToPass)
                //    {
                //        $status="Đã hoàn thành";
                //          $result="Đạt";
                //       }
            }
            else
            {
                
                
                $basicMark=GetQuizGradeByUserAndQuizID($user->id,$CFG->mofexams[$course->id][0],$submitDate);
                if($submitDate!=null)
                {
                    $date = date_create();
                    date_timestamp_set($date, $submitDate);
                    $newSubmitDate= date_format($date, 'm-d-Y H:i:s');
                    
                    
                }
                if($basicMark<$gradeToPass){
                    $status="Chưa hoàn thành";
                    $result="Không đạt";
                }
                else if($basicMark>=$gradeToPass)
                {
                    $status="Đã hoàn thành";
                    $result="Đạt";
                }
            }
            
            
            if (!$csv) {
                
                print '<td class="completion-progresscell">';
                print '<span class="report-status">'.$cohort.'</span>';
                print '</td>';
                print '<td class="completion-progresscell">';
                print '<span class="report-status">'.$status.'</span>';
                print '</td>';
                print '<td class="completion-progresscell">';
                print 'huhu';
                print '</td>';
                
                print '<td class="completion-progresscell">';
                print $basicMark;
                print '</td>';
                if($isMOF)
                {
                    
                    print '<td class="completion-progresscell">';
                    print $finalMark;
                    print '</td>';
                    
                    print '<td class="completion-progresscell">';
                    //echo "XXXX".$app;
                    if($app==1)
                        print '<input type="checkbox" checked="checked" name="chkByAdmin" id="chkByAdmin'.$user->id.'">';
                        else
                            print '<input type="checkbox"  name="chkByAdmin" id="chkByAdmin'.$user->id.'">';
                            print '</td>';
                            print '<td class="completion-progresscell"><textarea rows="5" id="txtComment'.$user->id.'" cols="10">'.$comment.'</textarea></td>';
                            print '<td class="completion-progresscell"><input type="button" onclick="return updateReport(document.getElementById(\'chkByAdmin'.$user->id.'\').checked,document.getElementById(\'txtComment'.$user->id.'\').value,'.$user->id.','.$course->id.');" class="btn btn-primary" value="Cập nhật"></td>';
                }
                else
                {
                    print '<td class="completion-progresscell">';
                    print date_format($date, 'd-m-Y H:i:s');
                    print '</td>';
                }
            }
            else
            {
                $cohort_sql = "
            select *
            from mdl232x0_cohort
            where id = $cohortid";
                $get_name = $DB->get_record_sql($cohort_sql,array());
                $newUser=GetUserProfileField($user->id);
                $row[] = $newUser->profile['ngaysinh'];
                $row[] = $newUser->profile['thangsinh'];
                if($isMOF)
                {
                    $row[]=$newUser->profile['namsinh'];
                    $row[]=$newUser->profile['gioitinh'];
                    $row[]=$newUser->phone1;
                    $row[]= $newUser->profile['cmtnd'];
                    
                    $row[]=$newUser->profile['ngaycapcmtnd'];
                    $row[]=$newUser->profile['noicapcmtnd'];
                    $row[]=$get_name->idnumber;
                    $row[]=$basicMark_A;
                }
                else
                {
                    
                    $row[]=$cohort->idnumber;
                    $row[]= $newUser->phone1;
                    
                    $row[]=$result;
                    $row[]=$basicMark;
                    $row[]=$newSubmitDate;//Date_format($newSubmitDate, "m/d/Y h:m:i");
                }
                $row[] = $result_A;
                $row[] = $basicMark_B;
                $row[] = $result_B;
                
                
                $agentInfo=GetAgentInfo($newUser->profile['cmtnd']);
                
                if(isset($agentInfo))
                {
                    $row[] = "";
                    $row[] = "";
                    $row[] = "total ".$total;
                    $row[] = "progress ".$progress;
                    $row[] = "";
                    $row[] = "";
                    $row[] = "";
                    $row[] = "";
                }
                else
                {
                    $row[] = "";
                    $row[] = "";
                    $row[] = "";
                    $row[] = "";
                    $row[] = "";
                    $row[] = "";
                    $row[] = "";
                    $row[] = "";
                }
                $row[]=$cohort_sql;
            }
            if ($csv) {
                $export->add_data($row);
            } else {
                print "<tr>";
            }
        }
        $row[6] = "total ".$total;
        $export->add_data($row);
        print '</table>';
        
        if ($csv) {
            $export->download_file();
        } else {
            echo '</tbody>';
        }
        
        //print $pagingbar;
        if($isMOF)
        {
            $csvurl = new moodle_url('/phlcohort/report/completion/index.php', array('course' => $course->id, 'format' => 'csv', 'cohortid' => $cohortid, 'ngay' => $ngay, 'thang' => $thang, 'nam' => $nam));
            $excelurl = new moodle_url('/phlcohort/report/completion/index.php', array('course' => $course->id, 'format' => 'excelcsv', 'cohortid' => $cohortid, 'ngay' => $ngay, 'thang' => $thang, 'nam' => $nam));
        }
        else
        {
            $csvurl = new moodle_url('/phlcohort/report/completion/index.php', array('course' => $course->id, 'format' => 'csv','cohortid' => $cohortid,'tungay' => $tungay, 'denngay' => $denngay));
            $excelurl = new moodle_url('/phlcohort/report/completion/index.php', array('course' => $course->id, 'format' => 'excelcsv','cohortid' => $cohortid, 'tungay' => $tungay, 'denngay' => $denngay));
        }
        
        print '<ul class="export-actions">';
        print '<li><a href="'.$csvurl->out().'">'.get_string('csvdownload','completion').'</a></li>';
        print '<li><a href="'.$excelurl->out().'">'.get_string('excelcsvdownload','completion').'</a></li>';
        print '</ul>';
        
        echo $OUTPUT->footer($course);
        
        // Trigger a report viewed event.
        $event = \report_completion\event\report_viewed::create(array('context' => $context));
        $event->trigger();
        
        function GetQuizGradeByUserAndQuizID($userid,$quizid,&$submitDate)
        {
            
            // Now we know the user is interested in reports. If they are interested in a
            // specific other user, try to send them to the most appropriate attempt review page.
            //echo $userid."-".$quizid;
            if ($userid)
            {
                
                // Work out which attempt is most significant from a grading point of view.
                $attempts = quiz_get_user_attempts($quizid, $userid);
                $attempt = null;
                
                $maxmark = 0;
                foreach ($attempts as $at) {
                    // Operator >=, since we want to most recent relevant attempt.
                    if ((float) $at->sumgrades >= $maxmark) {
                        $maxmark = $at->sumgrades;
                        $attempt = $at;
                        
                    }
                }
                //var_dump($attempt);
                if(isset($attempt))
                    $submitDate=$attempt->timefinish;
                    
                    // If the user can review the relevant attempt, redirect to it.
                    return round($maxmark);
            }
            
        }
        function GetAgentInfo($username)
        {
            global $DB;
            $records = $DB->get_records_sql("SELECT * FROM Agents_Info where  ID_Number like '%".$username."%'");
            foreach ($records as $record){
                return $record;
            }
            
        }
        function GetUserByCohortID($cohortid,$ngay,$thang,$nam)
        {
            global $DB;
            $userids='';
            $dateCondition=null;
            if($cohortid>0)
                $listUserID=$DB->get_records_sql('SELECT userid FROM mdl232x0_cohort_members,mdl232x0_cohort where mdl232x0_cohort.id=mdl232x0_cohort_members.cohortid and cohortid=?',array($cohortid));
                else
                {
                    if($ngay>0 && $ngay<10)
                        $dateCondition.="idnumber like '%(0$ngay.%-%'";
                        else if($ngay>=10)
                            $dateCondition.="idnumber like '%($ngay.%-%'";
                            
                            if($thang>0)
                            {
                                $dateCondition=$dateCondition!=null?$dateCondition." and ":$dateCondition;
                                if($thang>0 && $thang<10)
                                    $dateCondition.="idnumber like '%.0$thang.%-%'";
                                    else if($ngay>=10)
                                        $dateCondition.="idnumber like '%.$thang.%-%'";
                            }
                            if($nam>0)
                            {
                                $dateCondition=$dateCondition!=null?$dateCondition." and ":$dateCondition;
                                $dateCondition.="idnumber like '%.$nam%-%'";
                            }
                            //echo $dateCondition;
                            $listUserID=$DB->get_records_sql('SELECT userid FROM mdl232x0_cohort_members,mdl232x0_cohort where mdl232x0_cohort.id=mdl232x0_cohort_members.cohortid and '.$dateCondition);
                }
                //{
                //	echo "SELECT userid FROM mdl232x0_cohort_members,mdl232x0_cohort where mdl232x0_cohort.id=mdl232x0_cohort_members.cohortid and (cohortname like '%$dateFrom%' or cohortname like '%$dateTo%')';
                //	//$listUserID=$DB->get_records_sql('SELECT userid FROM mdl232x0_cohort_members,mdl232x0_cohort where mdl232x0_cohort.id=mdl232x0_cohort_members.cohortid and (cohortname like '''.$dateFrom.''' or cohortname like '''.$dateTo.''')');
                //}
                
                
                foreach ($listUserID as $record) {
                    $userids.=$record->userid.',';
                }
                $userids.="last";
                $userids=str_replace(",last","",$userids);
                return $userids;
                //echo $userids;
        }
        
        function GetCohortList($cohortID,$ngay="",$thang="",$nam="",$courseid)
        {
            global $DB;
            $select='<style type="text/css">.slsearch {        padding-right: 30px;        margin: 20px;        margin-left: 0px !important;        min-width: 200px;        height: 35px;       padding: 0px;        padding-left: 10px;        padding-right: 25px;    }</style>';
            $select.='<select id="slLop" class="availability-neg custom-select m-x-1 slsearch">';
            $select.="<option value='0'> --Tất cả Lớp-- </option>";
            $listCohorts=$DB->get_records_sql('SELECT * FROM mdl232x0_cohort order by idnumber asc');
            
            foreach ($listCohorts as $record) {
                $selected='';
                if($cohortID==$record->id)
                    $selected="selected='selected'";
                    $select.="<option $selected value='$record->id'>$record->idnumber</option>";
            }
            $select.="</select>";
            
            $slNgay="<select  id='slNgay'  class='availability-neg custom-select m-x-1 slsearch'><option value=''>Ngày</option>";
            for($i=1;$i<=31;$i++)
            {
                $selected='';
                if($ngay==$i)
                    $selected="selected='selected'";
                    $slNgay.="<option $selected value='$i'>$i</option>";
            }
            $slNgay.="</select>";
            
            $slThang="&nbsp;<select  id='slThang'  class='availability-neg custom-select m-x-1 slsearch'><option value=''>Tháng</option>";
            for($i=1;$i<=12;$i++)
            {
                $selected='';
                if($thang==$i)
                    $selected="selected='selected'";
                    $slThang.="<option $selected value='$i'>$i</option>";
            }
            $slThang.="</select>";
            
            $slNam="&nbsp;<select  id='slNam'  class='availability-neg custom-select m-x-1 slsearch'><option value=''>Năm</option>";
            for($i=2018;$i<=date("Y");$i++)
            {
                $selected='';
                if($nam==$i)
                    $selected="selected='selected'";
                    $slNam.="<option $selected value='$i'>$i</option>";
            }
            $slNam.="</select>";
            $submit='&nbsp;<input type="button" onclick="location.href=\'./index.php?course='.$courseid.'\' + \'&cohortid=\' + document.getElementById(\'slLop\').value + \'&ngay=\' + document.getElementById(\'slNgay\').value + \'&thang=\' + document.getElementById(\'slThang\').value + \'&nam=\' + document.getElementById(\'slNam\').value;"  class="btn btn-primary" value="Tìm">';
            
            $select.=$slNgay.$slThang.$slNam.$submit;
            
            
            
            return $select;
            //echo $userids;
        }
        
        function GetCohorByUserID($userID)
        {
            global $DB;
            
            $listRecords=$DB->get_records_sql('SELECT idnumber FROM mdl232x0_cohort_members,mdl232x0_cohort where mdl232x0_cohort.id=mdl232x0_cohort_members.cohortid and idnumber<>\'allstudent\' and userid=?',array($userID));
            
            $cohort='';
            foreach ($listRecords as $record) {
                if($cohort=='')
                    $cohort.=$record->idnumber;
                    else
                        $cohort.=", ".$record->idnumber;
            }
            
            return $cohort;
            
        }
        function GetCohorAddedTimeByUserID($userID)
        {
            global $DB;
            
            $listRecords=$DB->get_records_sql('SELECT idnumber,mdl232x0_cohort_members.timeadded FROM mdl232x0_cohort_members,mdl232x0_cohort where mdl232x0_cohort.id=mdl232x0_cohort_members.cohortid and idnumber<>\'allstudent\' and userid=?',array($userID));
            
            $cohort='';
            //echo 'SELECT idnumber,mdl232x0_cohort_members.timeadded FROM mdl232x0_cohort_members,mdl232x0_cohort where mdl232x0_cohort.id=mdl232x0_cohort_members.cohortid and idnumber<>\'allstudent\' and userid=?';
            foreach ($listRecords as $record) {
                $date = date_create();
                date_timestamp_set($date, $record->timeadded);
                $addeddate= date_format($date, 'd/m/Y H:i:s');
                if($cohort=='')
                    $cohort.=$record->idnumber."-".$addeddate;
                    else
                        $cohort.=", ".$record->idnumber."-".$addeddate;
            }
            
            return $cohort;
            
        }
        function GetUserProfileField($userid)
        {
            global $DB;
            $newUser = $DB->get_record('user', array('id' => $userid));
            profile_load_custom_fields($newUser);
            return $newUser;
        }
        function LoadPSSInfo($userid)
        {
            
            global $DB;
            $sql = "
   select data from mdl232x0_user_info_data where userid = $userid and fieldid = 7
";
            $basic_info = $DB->get_record_sql($sql);
            return $basic_info;
        }
        
        
        function GetIDULUsers($quizid,&$listULUsers,$fullame,$tungay,$denngay,$gradeToPass)
        {
            global $DB;
            $userids='';
            $dateCondition=null;
            //echo GetTimeStamp($tungay)."CCC";
            //$tungay=date_format(GetTimeStamp($tungay), 'm-d-Y');
            //$denngay=date_format(strtotime($denngay), 'm-d-Y');
            
            //echo date_format(strtotime($tungay), 'm-d-Y').'yyyxxx';
            
            //echo "SELECT userid,from_unixtime(timefinish,'%d%m%Y') as cdate FROM mdl232x0_quiz_attempts where quiz=".$quizid." and timefinish>=".GetTimeStamp($tungay)." and timefinish<=".GetTimeStamp($denngay);
            //echo "SELECT userid,timefinish FROM mdl232x0_quiz_attempts where quiz=".$quizid." and timefinish>=".GetTimeStamp($tungay,false)." and timefinish<=".GetTimeStamp($denngay,true);
            
            if($tungay!='' && $denngay!='')
            {
                
                $listUserID=$DB->get_records_sql("SELECT userid FROM mdl232x0_quiz_attempts where  quiz=".$quizid." and timefinish>=".GetTimeStamp($tungay,false)." and timefinish<=".GetTimeStamp($denngay,true)." group by userid having max(sumgrades)>=$gradeToPass");
                $listUserID2=$DB->get_records_sql("SELECT userid FROM mdl232x0_quiz_attempts where userid not in(SELECT userid FROM mdl232x0_quiz_attempts where sumgrades>=$gradeToPass) and  quiz=".$quizid." and timefinish>=".GetTimeStamp($tungay,false)." and timefinish<=".GetTimeStamp($denngay,true)." group by userid having max(sumgrades)<$gradeToPass");
            }
            else if($tungay!='')
            {
                $listUserID=$DB->get_records_sql("SELECT userid FROM mdl232x0_quiz_attempts where  quiz=".$quizid." and timefinish>=".GetTimeStamp($tungay,false)." group by userid having max(sumgrades)>=$gradeToPass");
                $listUserID2=$DB->get_records_sql("SELECT userid FROM mdl232x0_quiz_attempts where userid not in(SELECT userid FROM mdl232x0_quiz_attempts where sumgrades>=$gradeToPass) and  quiz=".$quizid." and timefinish>=".GetTimeStamp($tungay,false)." group by userid having max(sumgrades)<$gradeToPass");
            }
            else if($denngay!='')
            {
                $listUserID=$DB->get_records_sql("SELECT userid FROM mdl232x0_quiz_attempts where  quiz=".$quizid." and  timefinish<=".GetTimeStamp($denngay,true)." group by userid having max(sumgrades)>=$gradeToPass");
                $listUserID2=$DB->get_records_sql("SELECT userid FROM mdl232x0_quiz_attempts where userid not in(SELECT userid FROM mdl232x0_quiz_attempts where sumgrades>=$gradeToPass) and  quiz=".$quizid." and timefinish<=".GetTimeStamp($denngay,true)." group by userid having max(sumgrades)<$gradeToPass");
            }
            else
            {
                $listUserID=$DB->get_records_sql("SELECT userid FROM mdl232x0_quiz_attempts where  quiz=".$quizid." group by userid having max(sumgrades)>=$gradeToPass");
                $listUserID2=$DB->get_records_sql("SELECT userid FROM mdl232x0_quiz_attempts where userid not in(SELECT userid FROM mdl232x0_quiz_attempts where sumgrades>=$gradeToPass) and  quiz=".$quizid." group by userid having max(sumgrades)<$gradeToPass");
            }
            
            
            foreach ($listUserID as $record) {
                $userids.=$record->userid.',';
            }
            foreach ($listUserID2 as $record) {
                $userids.=$record->userid.',';
            }
            
            $userids.="last";
            $userids=str_replace(",last","",$userids);
            //echo $userids;
            return $userids;
            
        }
        function GetTimeStamp($MySqlDate,$isDateTo)
        {
            /*
             Take a date in yyyy-mm-dd format and return it to the user in a PHP timestamp
             Robin 06/10/1999
             */
            
            
            $date_array = explode("/",$MySqlDate); // split the array
            
            $var_year = $date_array[2];
            $var_month = $date_array[1];
            $var_day = $date_array[0];
            /*
             if($isDateTo)
             {
             $var_day +=1;
             if($var_month = 12)
             {
             $var_month=1;
             $var_year+=1;
             }
             }
             
             return strtotime($var_year."-".$var_month."-".$var_day);
             */
            
            $temp=date("Y-m-d", strtotime($var_year."-".$var_month."-".$var_day));
            if($isDateTo)
                $var_timestamp = mktime(23,59,59,$var_month,$var_day,$var_year);
                else
                    $var_timestamp = mktime(0,0,1,$var_month,$var_day,$var_year);
                    //return($var_day); // return it to the user
                    //echo $var_timestamp."XXX";
                    return $var_timestamp;
        }
        function SearchControls($courseid,$fullname,$tungay,$denngay)
        {
            $html='<link rel="stylesheet" href="//code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css">';
            $html.='<link rel="stylesheet" href="/resources/demos/style.css">';
            $html.=' <script src="https://code.jquery.com/jquery-1.12.4.js"></script>';
            $html.=' <script src="https://code.jquery.com/ui/1.12.1/jquery-ui.js"></script>';
            $html.=' <script>';
            $html.=' $( function() {';
            $html.='	$("#tungay").datepicker();';
            $html.='	$("#denngay").datepicker();';
            $html.='	$("#tungay").datepicker("option", "dateFormat","dd/mm/yy");';
            $html.='	$("#denngay").datepicker("option", "dateFormat","dd/mm/yy");';
            $html.=' } );';
            $html.=' </script>';
            $html.='<style type="text/css">.tblsearch td {padding-right:15px;}</style>';
            $html.="<table class='tblsearch'><tr><td>&nbsp;</td></tr>";
            $html.="<tr><td>Từ ngày";
            if($tungay!='' && false)
                $html.="<input  id='tungay'  class='form-control m-r-1' value='". date("d/m/Y", strtotime($tungay))."' />";
                else
                    $html.="<input  id='tungay'  class='form-control m-r-1' value='' />";
                    
                    $html.="</td><td>Đến ngày";
                    if($denngay!='' && false)
                        $html.="<input  id='denngay'  class='form-control m-r-1' value='".date("d/m/Y", strtotime($denngay))."' />";
                        else
                            $html.="<input  id='denngay'  class='form-control m-r-1' value='' />";
                            
                            $html.="</td>";
                            $html.="<td style='padding-top:18px;'>";
                            $html.='&nbsp;<input type="button" onclick="location.href=\'./index.php?course='.$courseid.'\' + \'&tungay=\' + document.getElementById(\'tungay\').value + \'&denngay=\' + document.getElementById(\'denngay\').value;"  class="btn btn-primary" value="Tìm">';
                            $html.="</td></tr>";
                            $html.="<tr><td>&nbsp;</td></tr>";
                            $html.="</table>";
                            
                            return $html;
                            
        }
