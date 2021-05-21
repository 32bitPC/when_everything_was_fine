$searchkhoahoc = array('ch.khoahoc' => optional_param('khoahoc',0, PARAM_INT));
if ($showall) {
    $cohorts = cohort_get_all_phl_cohorts($page, 25, $searchquery,$searchkhoahoc);
} else {
    ;//$cohorts = cohort_get_cohorts($context->id, $page, 25, $searchquery);
}


function cohort_get_all_phl_cohorts($page = 0, $perpage = 250, $searchkhoahoc = '') {
    global $DB;
    
    $fields = "SELECT c.*,ch.ngayhoc,ch.ngaythi,ch.giothi,ch.thangnam,ch.khoahoc,mi.tenmien,kv.tenkhuvuc,co.fullname,qh.tenquanhuyen,qh.khuvuc,ch.sonha,trainer";
    $countfields = "SELECT COUNT(*)";
    $sql = " FROM {cohort} c
             JOIN {cohortphl} ch ON c.id = ch.cohortid
             JOIN {course} co ON ch.khoahoc = co.id
             JOIN {cohortphl_quanhuyen} qh ON ch.khuvuc = qh.id
             JOIN {cohortphl_khuvuc} kv ON qh.khuvuc = kv.id
             JOIN {cohortphl_mien} mi ON kv.mien = mi.id ";
    
    $params = array();
    //var_dump($search);
    if (!empty($search) && $search['ngayhoctu']>0)
    {
        $from=strtotime($search['ngayhoctu']['year'].'-'.$search['ngayhoctu']['month'].'-'.$search['ngayhoctu']['day']);
        $to=strtotime($search['ngayhocden']['year'].'-'.$search['ngayhocden']['month'].'-'.$search['ngayhocden']['day']);
        $wheresql = ' WHERE c.visible=1 and ngayhoc>='.$from.' and ngayhoc<='.$to;
        $wheresql .=" and (c.idnumber like '%".$search['c.idnumber']."%')";
        $wheresql .=  " and ch.khoahoc = ".$searchkhoahoc['ch.khoahoc'];
        
    }
    else
        $wheresql = " WHERE c.visible=1";
        
        
        $totalcohorts = $allcohorts = $DB->count_records_sql($countfields . $sql . $wheresql, $params);
        
        if (!empty($search)) {
            list($searchcondition, $searchparams) = cohort_get_phl_search_query($search, '');
            $wheresql .= ($wheresql ? ' AND ' : ' WHERE ') . $searchcondition;
            $params = array_merge($params, $searchparams);
            $totalcohorts = $DB->count_records_sql($countfields . $sql . $wheresql, $params);
            //var_dump($params);
        }
        
        $order = " ORDER BY ch.thangnam DESC";
        // echo $fields . $sql . $wheresql . $order;
        $cohorts = $DB->get_records_sql($fields . $sql . $wheresql . $order, $params, $page*$perpage, $perpage);
        
        // Preload used contexts, they will be used to check view/manage/assign capabilities and display categories names.
        foreach (array_keys($cohorts) as $key) {
            context_helper::preload_from_record($cohorts[$key]);
        }
        
        return array('totalcohorts' => $totalcohorts, 'cohorts' => $cohorts, 'allcohorts' => $allcohorts);
}
