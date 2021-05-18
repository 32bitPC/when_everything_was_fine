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
 * Cohort related management functions, this file needs to be included manually.
 *
 * @package    core_cohort
 * @copyright  2010 Petr Skoda  {@link http://skodak.org}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../config.php');
require($CFG->dirroot.'/cohort/lib.php');
require($CFG->dirroot.'/phlcohort/lib.php');
require($CFG->dirroot.'/phlcohort/search_form_manager.php');
require_once($CFG->libdir.'/adminlib.php');

$contextid = optional_param('contextid', 1, PARAM_INT);
$page = optional_param('page', 0, PARAM_INT);
$searchquery  = optional_param('search', '', PARAM_RAW);
$showall = optional_param('showall', true, PARAM_BOOL);

require_login();

if ($contextid) {
    $context = context::instance_by_id($contextid, MUST_EXIST);
} else {
    $context = context_system::instance();
}

if ($context->contextlevel != CONTEXT_COURSECAT and $context->contextlevel != CONTEXT_SYSTEM) {
    print_error('invalidcontext');
}

$category = null;
if ($context->contextlevel == CONTEXT_COURSECAT) {
    $category = $DB->get_record('course_categories', array('id'=>$context->instanceid), '*', MUST_EXIST);
}

$manager = has_capability('moodle/cohort:manage', $context);
$canassign = has_capability('moodle/cohort:assign', $context);
if (!$manager) {
    require_capability('moodle/cohort:view', $context);
}

$strcohorts = get_string('cohorts', 'cohort');

if ($category) {
    $PAGE->set_pagelayout('admin');
    $PAGE->set_context($context);
    $PAGE->set_url('/phlcohort/manager.php', array('contextid'=>$context->id));
    $PAGE->set_title($strcohorts);
    $PAGE->set_heading($COURSE->fullname);
    $showall = false;
} else {
    admin_externalpage_setup('cohorts', '', null, '', array('pagelayout'=>'report'));
}

echo $OUTPUT->header();

$dateValue =date("Y-m-d");                            
$day = date_parse($dateValue)['day']; 

$yr = date_parse($dateValue)['year']; 
$mon = date_parse($dateValue)['month'];                 
$arrayTu = array('year' =>$yr,'month' =>$mon,'day' =>$day);
$mon=$mon+1;
if($mon==13)
{
    $mon=1;
    $yr=$yr+1;
}
$arrayDen = array('year' =>$yr,'month' =>$mon,'day' =>$day);

$searchquery=array(
    'c.idnumber' => optional_param('idnumber','', PARAM_TEXT),
    'ngayhoctu'=>optional_param_array('ngayhoctu', $arrayTu, PARAM_INT),
    'ngayhocden'=>optional_param_array('ngayhocden', $arrayDen, PARAM_INT),
    //'mien' => optional_param('mien',0, PARAM_INT),
    'qh.khuvuc' => optional_param('khuvuc',0, PARAM_INT),
    'ch.khoahoc' => optional_param('khoahoc',0, PARAM_INT));    


if ($showall) {
    $cohorts = cohort_get_all_phl_cohorts($page, 25, $searchquery);
} else {
    ;//$cohorts = cohort_get_cohorts($context->id, $page, 25, $searchquery);
}

$count = '';
if ($cohorts['allcohorts'] > 0) {
    if ($searchquery === '') {
        $count = ' ('.$cohorts['allcohorts'].')';
    } else {
        $count = ' ('.$cohorts['totalcohorts'].'/'.$cohorts['allcohorts'].')';
    }
}

echo $OUTPUT->heading(get_string('cohortsin', 'cohort', $context->get_context_name()).$count);

$params = array('page' => $page);
if ($contextid) {
    $params['contextid'] = $contextid;
}
if ($searchquery) { 
    ;//$params['search'] = $searchquery;
}
if ($showall) {
    $params['showall'] = true;
}
$baseurl = new moodle_url('/phlcohort/manager.php', $params);

if ($editcontrols = cohort_edit_controls_phl($context, $baseurl)) {
    echo $OUTPUT->render($editcontrols);
}
echo "
<form autocomplete='off' action='http://localhost:8083/html/monalisa/manager.php' method='post' accept-charset='utf-8' id='mform1' class='mform'>
	<div style='display: none;'><input name='sesskey' type='hidden' value='AGb093f0RD'>
<input name='_qf__cohort_search_form' type='hidden' value='1'>
<input name='mform_isexpanded_id_cohortfileuploadform' type='hidden' value='1'>
</div>


	<fieldset class='clearfix collapsible' id='id_cohortfileuploadform'>
		<legend class='ftoggler'><a href='#' class='fheader' role='button' aria-controls='id_cohortfileuploadform' aria-expanded='true'>Find offline class</a></legend>
		<div class='fcontainer clearfix'>
		<div class='col-md-12'>

			<div class='col-md-6'>
				<div class='form-group row  fitem   ' data-groupname='ngayhoctu'>
    <div class='col-md-3'>
        <span class='pull-xs-right text-nowrap'>



        </span>
        <label class='col-form-label d-inline ' for='id_ngayhoctu'>
            Open date
        </label>
    </div>
    <div class='col-md-9 form-inline felement' data-fieldtype='date_selector'>
        <span class='fdate_selector' id='yui_3_17_2_1_1621300382159_114'>

            <div class='form-group  fitem  '>
    <label class='col-form-label sr-only' for='id_ngayhoctu_day'>
        Day

  </label>
    <span data-fieldtype='select'>
    <select class='custom-select

                   ' name='ngayhoctu[day]' id='id_ngayhoctu_day'>
        <option value='1'>1</option>
        <option value='2'>2</option>
        <option value='3'>3</option>
        <option value='4'>4</option>
        <option value='5'>5</option>
        <option value='6'>6</option>
        <option value='7'>7</option>
        <option value='8'>8</option>
        <option value='9'>9</option>
        <option value='10'>10</option>
        <option value='11'>11</option>
        <option value='12'>12</option>
        <option value='13'>13</option>
        <option value='14'>14</option>
        <option value='15'>15</option>
        <option value='16'>16</option>
        <option value='17'>17</option>
        <option value='18' selected=''>18</option>
        <option value='19'>19</option>
        <option value='20'>20</option>
        <option value='21'>21</option>
        <option value='22'>22</option>
        <option value='23'>23</option>
        <option value='24'>24</option>
        <option value='25'>25</option>
        <option value='26'>26</option>
        <option value='27'>27</option>
        <option value='28'>28</option>
        <option value='29'>29</option>
        <option value='30'>30</option>
        <option value='31'>31</option>
    </select>
    </span>
    <div class='form-control-feedback' id='id_error_ngayhoctu[day]' style='display: none;'>

    </div>
</div>
            &nbsp;
            <div class='form-group  fitem  '>
    <label class='col-form-label sr-only' for='id_ngayhoctu_month'>
        Month


    </label>
    <span data-fieldtype='select'>
    <select class='custom-select

                   ' name='ngayhoctu[month]' id='id_ngayhoctu_month'>
        <option value='1'>January</option>
        <option value='2'>February</option>
        <option value='3'>March</option>
        <option value='4'>April</option>
        <option value='5' selected=''>May</option>
        <option value='6'>June</option>
        <option value='7'>July</option>
        <option value='8'>August</option>
        <option value='9'>September</option>
        <option value='10'>October</option>
        <option value='11'>November</option>
        <option value='12'>December</option>
    </select>
    </span>
    <div class='form-control-feedback' id='id_error_ngayhoctu[month]' style='display: none;'>

    </div>
</div>
            &nbsp;
            <div class='form-group  fitem  '>
    <label class='col-form-label sr-only' for='id_ngayhoctu_year'>
        Year


    </label>
    <span data-fieldtype='select'>
    <select class='custom-select

                   ' name='ngayhoctu[year]' id='id_ngayhoctu_year'>
        <option value='1900'>1900</option>
        <option value='1901'>1901</option>
        <option value='1902'>1902</option>
        <option value='1903'>1903</option>
        <option value='1904'>1904</option>
        <option value='1905'>1905</option>
        <option value='1906'>1906</option>
        <option value='1907'>1907</option>
        <option value='1908'>1908</option>
        <option value='1909'>1909</option>
        <option value='1910'>1910</option>
        <option value='1911'>1911</option>
        <option value='1912'>1912</option>
        <option value='1913'>1913</option>
        <option value='1914'>1914</option>
        <option value='1915'>1915</option>
        <option value='1916'>1916</option>
        <option value='1917'>1917</option>
        <option value='1918'>1918</option>
        <option value='1919'>1919</option>
        <option value='1920'>1920</option>
        <option value='1921'>1921</option>
        <option value='1922'>1922</option>
        <option value='1923'>1923</option>
        <option value='1924'>1924</option>
        <option value='1925'>1925</option>
        <option value='1926'>1926</option>
        <option value='1927'>1927</option>
        <option value='1928'>1928</option>
        <option value='1929'>1929</option>
        <option value='1930'>1930</option>
        <option value='1931'>1931</option>
        <option value='1932'>1932</option>
        <option value='1933'>1933</option>
        <option value='1934'>1934</option>
        <option value='1935'>1935</option>
        <option value='1936'>1936</option>
        <option value='1937'>1937</option>
        <option value='1938'>1938</option>
        <option value='1939'>1939</option>
        <option value='1940'>1940</option>
        <option value='1941'>1941</option>
        <option value='1942'>1942</option>
        <option value='1943'>1943</option>
        <option value='1944'>1944</option>
        <option value='1945'>1945</option>
        <option value='1946'>1946</option>
        <option value='1947'>1947</option>
        <option value='1948'>1948</option>
        <option value='1949'>1949</option>
        <option value='1950'>1950</option>
        <option value='1951'>1951</option>
        <option value='1952'>1952</option>
        <option value='1953'>1953</option>
        <option value='1954'>1954</option>
        <option value='1955'>1955</option>
        <option value='1956'>1956</option>
        <option value='1957'>1957</option>
        <option value='1958'>1958</option>
        <option value='1959'>1959</option>
        <option value='1960'>1960</option>
        <option value='1961'>1961</option>
        <option value='1962'>1962</option>
        <option value='1963'>1963</option>
        <option value='1964'>1964</option>
        <option value='1965'>1965</option>
        <option value='1966'>1966</option>
        <option value='1967'>1967</option>
        <option value='1968'>1968</option>
        <option value='1969'>1969</option>
        <option value='1970'>1970</option>
        <option value='1971'>1971</option>
        <option value='1972'>1972</option>
        <option value='1973'>1973</option>
        <option value='1974'>1974</option>
        <option value='1975'>1975</option>
        <option value='1976'>1976</option>
        <option value='1977'>1977</option>
        <option value='1978'>1978</option>
        <option value='1979'>1979</option>
        <option value='1980'>1980</option>
        <option value='1981'>1981</option>
        <option value='1982'>1982</option>
        <option value='1983'>1983</option>
        <option value='1984'>1984</option>
        <option value='1985'>1985</option>
        <option value='1986'>1986</option>
        <option value='1987'>1987</option>
        <option value='1988'>1988</option>
        <option value='1989'>1989</option>
        <option value='1990'>1990</option>
        <option value='1991'>1991</option>
        <option value='1992'>1992</option>
        <option value='1993'>1993</option>
        <option value='1994'>1994</option>
        <option value='1995'>1995</option>
        <option value='1996'>1996</option>
        <option value='1997'>1997</option>
        <option value='1998'>1998</option>
        <option value='1999'>1999</option>
        <option value='2000'>2000</option>
        <option value='2001'>2001</option>
        <option value='2002'>2002</option>
        <option value='2003'>2003</option>
        <option value='2004'>2004</option>
        <option value='2005'>2005</option>
        <option value='2006'>2006</option>
        <option value='2007'>2007</option>
        <option value='2008'>2008</option>
        <option value='2009'>2009</option>
        <option value='2010'>2010</option>
        <option value='2011'>2011</option>
        <option value='2012'>2012</option>
        <option value='2013'>2013</option>
        <option value='2014'>2014</option>
        <option value='2015'>2015</option>
        <option value='2016'>2016</option>
        <option value='2017'>2017</option>
        <option value='2018'>2018</option>
        <option value='2019'>2019</option>
        <option value='2020'>2020</option>
        <option value='2021' selected=''>2021</option>
        <option value='2022'>2022</option>
        <option value='2023'>2023</option>
        <option value='2024'>2024</option>
        <option value='2025'>2025</option>
        <option value='2026'>2026</option>
        <option value='2027'>2027</option>
        <option value='2028'>2028</option>
        <option value='2029'>2029</option>
        <option value='2030'>2030</option>
        <option value='2031'>2031</option>
        <option value='2032'>2032</option>
        <option value='2033'>2033</option>
        <option value='2034'>2034</option>
        <option value='2035'>2035</option>
        <option value='2036'>2036</option>
        <option value='2037'>2037</option>
        <option value='2038'>2038</option>
        <option value='2039'>2039</option>
        <option value='2040'>2040</option>
        <option value='2041'>2041</option>
        <option value='2042'>2042</option>
        <option value='2043'>2043</option>
        <option value='2044'>2044</option>
        <option value='2045'>2045</option>
        <option value='2046'>2046</option>
        <option value='2047'>2047</option>
        <option value='2048'>2048</option>
        <option value='2049'>2049</option>
        <option value='2050'>2050</option>
    </select>
    </span>
    <div class='form-control-feedback' id='id_error_ngayhoctu[year]' style='display: none;'>

    </div>
</div>
            &nbsp;
            <a class='visibleifjs' name='ngayhoctu[calendar]' href='#' id='id_ngayhoctu_calendar'><i class='icon fa fa-calendar fa-fw ' aria-hidden='true' title='Calendar' aria-label='Calendar'></i></a>
        </span>
        <div class='form-control-feedback' id='id_error_' style='display: none;'>

        </div>
    </div>
</div></div>
<div class='col-md-6'><div class='form-group row  fitem   ' data-groupname='ngayhocden'>
    <div class='col-md-3'>
        <span class='pull-xs-right text-nowrap'>



        </span>
        <label class='col-form-label d-inline ' for='id_ngayhocden'>
            To
        </label>
    </div>
    <div class='col-md-9 form-inline felement' data-fieldtype='date_selector'>
        <span class='fdate_selector' id='yui_3_17_2_1_1621300382159_131'>

            <div class='form-group  fitem  '>
    <label class='col-form-label sr-only' for='id_ngayhocden_day'>
        Day


    </label>
    <span data-fieldtype='select'>
    <select class='custom-select

                   ' name='ngayhocden[day]' id='id_ngayhocden_day'>
        <option value='1'>1</option>
        <option value='2'>2</option>
        <option value='3'>3</option>
        <option value='4'>4</option>
        <option value='5'>5</option>
        <option value='6'>6</option>
        <option value='7'>7</option>
        <option value='8'>8</option>
        <option value='9'>9</option>
        <option value='10'>10</option>
        <option value='11'>11</option>
        <option value='12'>12</option>
        <option value='13'>13</option>
        <option value='14'>14</option>
        <option value='15'>15</option>
        <option value='16'>16</option>
        <option value='17'>17</option>
        <option value='18' selected=''>18</option>
        <option value='19'>19</option>
        <option value='20'>20</option>
        <option value='21'>21</option>
        <option value='22'>22</option>
        <option value='23'>23</option>
        <option value='24'>24</option>
        <option value='25'>25</option>
        <option value='26'>26</option>
        <option value='27'>27</option>
        <option value='28'>28</option>
        <option value='29'>29</option>
        <option value='30'>30</option>
        <option value='31'>31</option>
    </select>
    </span>
    <div class='form-control-feedback' id='id_error_ngayhocden[day]' style='display: none;'>

    </div>
</div>
            &nbsp;
            <div class='form-group  fitem  '>
    <label class='col-form-label sr-only' for='id_ngayhocden_month'>
        Month


    </label>
    <span data-fieldtype='select'>
    <select class='custom-select

                   ' name='ngayhocden[month]' id='id_ngayhocden_month'>
        <option value='1'>January</option>
        <option value='2'>February</option>
        <option value='3'>March</option>
        <option value='4'>April</option>
        <option value='5'>May</option>
        <option value='6' selected=''>June</option>
        <option value='7'>July</option>
        <option value='8'>August</option>
        <option value='9'>September</option>
        <option value='10'>October</option>
        <option value='11'>November</option>
        <option value='12'>December</option>
    </select>
    </span>
    <div class='form-control-feedback' id='id_error_ngayhocden[month]' style='display: none;'>

    </div>
</div>
            &nbsp;
            <div class='form-group  fitem  '>
    <label class='col-form-label sr-only' for='id_ngayhocden_year'>
        Year


    </label>
    <span data-fieldtype='select'>
    <select class='custom-select

                   ' name='ngayhocden[year]' id='id_ngayhocden_year'>
        <option value='1900'>1900</option>
        <option value='1901'>1901</option>
        <option value='1902'>1902</option>
        <option value='1903'>1903</option>
        <option value='1904'>1904</option>
        <option value='1905'>1905</option>
        <option value='1906'>1906</option>
        <option value='1907'>1907</option>
        <option value='1908'>1908</option>
        <option value='1909'>1909</option>
        <option value='1910'>1910</option>
        <option value='1911'>1911</option>
        <option value='1912'>1912</option>
        <option value='1913'>1913</option>
        <option value='1914'>1914</option>
        <option value='1915'>1915</option>
        <option value='1916'>1916</option>
        <option value='1917'>1917</option>
        <option value='1918'>1918</option>
        <option value='1919'>1919</option>
        <option value='1920'>1920</option>
        <option value='1921'>1921</option>
        <option value='1922'>1922</option>
        <option value='1923'>1923</option>
        <option value='1924'>1924</option>
        <option value='1925'>1925</option>
        <option value='1926'>1926</option>
        <option value='1927'>1927</option>
        <option value='1928'>1928</option>
        <option value='1929'>1929</option>
        <option value='1930'>1930</option>
        <option value='1931'>1931</option>
        <option value='1932'>1932</option>
        <option value='1933'>1933</option>
        <option value='1934'>1934</option>
        <option value='1935'>1935</option>
        <option value='1936'>1936</option>
        <option value='1937'>1937</option>
        <option value='1938'>1938</option>
        <option value='1939'>1939</option>
        <option value='1940'>1940</option>
        <option value='1941'>1941</option>
        <option value='1942'>1942</option>
        <option value='1943'>1943</option>
        <option value='1944'>1944</option>
        <option value='1945'>1945</option>
        <option value='1946'>1946</option>
        <option value='1947'>1947</option>
        <option value='1948'>1948</option>
        <option value='1949'>1949</option>
        <option value='1950'>1950</option>
        <option value='1951'>1951</option>
        <option value='1952'>1952</option>
        <option value='1953'>1953</option>
        <option value='1954'>1954</option>
        <option value='1955'>1955</option>
        <option value='1956'>1956</option>
        <option value='1957'>1957</option>
        <option value='1958'>1958</option>
        <option value='1959'>1959</option>
        <option value='1960'>1960</option>
        <option value='1961'>1961</option>
        <option value='1962'>1962</option>
        <option value='1963'>1963</option>
        <option value='1964'>1964</option>
        <option value='1965'>1965</option>
        <option value='1966'>1966</option>
        <option value='1967'>1967</option>
        <option value='1968'>1968</option>
        <option value='1969'>1969</option>
        <option value='1970'>1970</option>
        <option value='1971'>1971</option>
        <option value='1972'>1972</option>
        <option value='1973'>1973</option>
        <option value='1974'>1974</option>
        <option value='1975'>1975</option>
        <option value='1976'>1976</option>
        <option value='1977'>1977</option>
        <option value='1978'>1978</option>
        <option value='1979'>1979</option>
        <option value='1980'>1980</option>
        <option value='1981'>1981</option>
        <option value='1982'>1982</option>
        <option value='1983'>1983</option>
        <option value='1984'>1984</option>
        <option value='1985'>1985</option>
        <option value='1986'>1986</option>
        <option value='1987'>1987</option>
        <option value='1988'>1988</option>
        <option value='1989'>1989</option>
        <option value='1990'>1990</option>
        <option value='1991'>1991</option>
        <option value='1992'>1992</option>
        <option value='1993'>1993</option>
        <option value='1994'>1994</option>
        <option value='1995'>1995</option>
        <option value='1996'>1996</option>
        <option value='1997'>1997</option>
        <option value='1998'>1998</option>
        <option value='1999'>1999</option>
        <option value='2000'>2000</option>
        <option value='2001'>2001</option>
        <option value='2002'>2002</option>
        <option value='2003'>2003</option>
        <option value='2004'>2004</option>
        <option value='2005'>2005</option>
        <option value='2006'>2006</option>
        <option value='2007'>2007</option>
        <option value='2008'>2008</option>
        <option value='2009'>2009</option>
        <option value='2010'>2010</option>
        <option value='2011'>2011</option>
        <option value='2012'>2012</option>
        <option value='2013'>2013</option>
        <option value='2014'>2014</option>
        <option value='2015'>2015</option>
        <option value='2016'>2016</option>
        <option value='2017'>2017</option>
        <option value='2018'>2018</option>
        <option value='2019'>2019</option>
        <option value='2020'>2020</option>
        <option value='2021' selected=''>2021</option>
        <option value='2022'>2022</option>
        <option value='2023'>2023</option>
        <option value='2024'>2024</option>
        <option value='2025'>2025</option>
        <option value='2026'>2026</option>
        <option value='2027'>2027</option>
        <option value='2028'>2028</option>
        <option value='2029'>2029</option>
        <option value='2030'>2030</option>
        <option value='2031'>2031</option>
        <option value='2032'>2032</option>
        <option value='2033'>2033</option>
        <option value='2034'>2034</option>
        <option value='2035'>2035</option>
        <option value='2036'>2036</option>
        <option value='2037'>2037</option>
        <option value='2038'>2038</option>
        <option value='2039'>2039</option>
        <option value='2040'>2040</option>
        <option value='2041'>2041</option>
        <option value='2042'>2042</option>
        <option value='2043'>2043</option>
        <option value='2044'>2044</option>
        <option value='2045'>2045</option>
        <option value='2046'>2046</option>
        <option value='2047'>2047</option>
        <option value='2048'>2048</option>
        <option value='2049'>2049</option>
        <option value='2050'>2050</option>
    </select>
    </span>
    <div class='form-control-feedback' id='id_error_ngayhocden[year]' style='display: none;'>

    </div>
</div>
            &nbsp;
            <a class='visibleifjs' name='ngayhocden[calendar]' href='#' id='id_ngayhocden_calendar'><i class='icon fa fa-calendar fa-fw ' aria-hidden='true' title='Calendar' aria-label='Calendar'></i></a>
        </span>
        <div class='form-control-feedback' id='id_error_' style='display: none;'>

        </div>
    </div>
</div></div></div>
<div class='col-md-6'><div class='form-group row  fitem femptylabel  '>
    <div class='col-md-3'>
        <span class='pull-xs-right text-nowrap'>
            
            
            
        </span>
        <label class='col-form-label d-inline ' for='id_submit'>
            
        </label>
    </div>
    <div class='col-md-9 form-inline felement' data-fieldtype='submit'>
            <input type='submit' class='btn
                    btn-primary
                    
                    ' name='submit' id='id_submit' value='Find offline class'>
        <div class='form-control-feedback' id='id_error_submit' style='display: none;'>
            
        </div>
    </div>
</div></div>
		</div></fieldset>
</form>

";

$from = strtotime($searchquery['ngayhoctu']['year']."/".$searchquery['ngayhoctu']['month']."/".$searchquery['ngayhoctu']['day']);
$to = strtotime($searchquery['ngayhocden']['year']."/".$searchquery['ngayhocden']['month']."/".$searchquery['ngayhocden']['day']);
echo $from." and ".$to."<br>";
echo gettype($from);
echo "<br>";
$sql = "
select * from mdl232x0_user where timecreated between $from and $to
";
echo $sql."<br>";
$list_users = $DB->get_records_sql($sql,array());
foreach($list_users as $u){
    echo $u->username."<br>";
}
//$table->colclasses = array('leftalign name', 'leftalign id', 'leftalign description', 'leftalign size','centeralign source');
/*if ($showall) {
    array_unshift($table->head, get_string('category'));
    array_unshift($table->colclasses, 'leftalign category');
}*/

echo $OUTPUT->footer();
