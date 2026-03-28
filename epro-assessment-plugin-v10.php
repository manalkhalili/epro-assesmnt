<?php
/**
 * Plugin Name: EPRO IPO Assessment Tool
 * Description: أداة تقييم جاهزية الطرح العام للشركات
 * Version: 9.0 - Enhanced Report Design
 * Author: Manal Khalili / EPRO Business Development
 */
if (!defined('ABSPATH')) exit;

register_activation_hook(__FILE__, 'epro_create_table');
function epro_create_table() {
    global $wpdb;
    $table = $wpdb->prefix . 'epro_assessments';
    $charset = $wpdb->get_charset_collate();
    $sql = "CREATE TABLE IF NOT EXISTS $table (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        lead_name VARCHAR(255), lead_email VARCHAR(255), lead_phone VARCHAR(50),
        company_name VARCHAR(255), sector VARCHAR(100), country VARCHAR(100),
        founded_year INT, annual_revenue DECIMAL(20,2), employees INT, assessment_goal VARCHAR(100),
        rev_current DECIMAL(20,2), rev_prev DECIMAL(20,2), net_profit DECIMAL(20,2), gross_profit DECIMAL(20,2),
        total_assets DECIMAL(20,2), total_liabilities DECIMAL(20,2), equity DECIMAL(20,2),
        current_assets DECIMAL(20,2), current_liabilities DECIMAL(20,2), cash DECIMAL(20,2), total_debt DECIMAL(20,2),
        governance_data TEXT, ipo_data TEXT, results_json TEXT,
        financial_score DECIMAL(5,2), governance_score DECIMAL(5,2), ipo_score DECIMAL(5,2),
        overall_score DECIMAL(5,2), ipo_blocked TINYINT(1) DEFAULT 0, overall_class VARCHAR(100)
    ) $charset;";
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);
}

add_shortcode('epro_assessment', 'epro_render_form');
add_shortcode('epro_assessment_auto', 'epro_render_auto');
function epro_render_auto() {
    $lang = 'en';
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    if (preg_match('#/(ar)[/?#]#', $uri) || preg_match('#/(ar)$#', $uri) || substr($uri,0,4)==='/ar/') {
        $lang = 'ar';
    } elseif (function_exists('trp_get_current_language')) {
        $trp = trp_get_current_language();
        if ($trp && strpos($trp,'ar')!==false) $lang='ar';
    } elseif (strpos(get_locale(),'ar')!==false) {
        $lang='ar';
    }
    return ($lang==='ar') ? epro_render_form() : epro_render_form_en();
}
add_action('wp_ajax_epro_submit', 'epro_handle_submit');
add_action('wp_ajax_nopriv_epro_submit', 'epro_handle_submit');
add_action('admin_menu', 'epro_admin_menu');
add_action('admin_post_epro_export_csv', 'epro_export_csv');


// ==============================
// EMAIL BUILDER ROWS
// ==============================

function epro_fin_rows($isAr, $scores) {
    $align = $isAr ? 'right' : 'left';
    $items = $isAr ? [
        ['إجمالي الإيرادات – السنة الحالية',   $scores['revCurrent']],
        ['إجمالي الإيرادات – السنة السابقة',    $scores['revPrev']],
        ['صافي الربح',                           $scores['net']],
        ['إجمالي الربح',                         $scores['gross']],
        ['إجمالي الأصول',                        $scores['assets']],
        ['حقوق الملكية',                         $scores['equity']],
        ['الأصول المتداولة',                     $scores['curA']],
        ['الالتزامات المتداولة',                 $scores['curL']],
        ['النقد وما في حكمه',                    $scores['cash']],
        ['إجمالي الديون',                        $scores['debt']],
    ] : [
        ['Total Revenue – Current Year',    $scores['revCurrent']],
        ['Total Revenue – Previous Year',   $scores['revPrev']],
        ['Net Profit',                      $scores['net']],
        ['Gross Profit',                    $scores['gross']],
        ['Total Assets',                    $scores['assets']],
        ['Shareholders Equity',             $scores['equity']],
        ['Current Assets',                  $scores['curA']],
        ['Current Liabilities',             $scores['curL']],
        ['Cash & Equivalents',              $scores['cash']],
        ['Total Debt',                      $scores['debt']],
    ];
    $html = '';
    foreach ($items as $i => $row) {
        $bg = $i % 2 === 0 ? '#F8FAFD' : '#FFFFFF';
        $val = number_format(floatval($row[1]), 2);
        $html .= "<tr>
            <td style='padding:9px 14px;font-size:13px;color:#1A2B4A;border-bottom:1px solid #EEF4FF;text-align:{$align};background:{$bg};'>{$row[0]}</td>
            <td style='padding:9px 14px;font-size:13px;font-weight:700;color:#1A3A6B;border-bottom:1px solid #EEF4FF;text-align:center;background:{$bg};'>{$val}</td>
        </tr>";
    }
    return $html;
}

function epro_gov_rows($isAr, $govData) {
    $ar = [
        'g1'=>'الفصل بين الملكية والإدارة التنفيذية',
        'g2'=>'وجود تدقيق داخلي (Internal Audit)',
        'g3'=>'وجود لجنة مراجعة (Audit Committee)',
        'g5'=>'سياسة إدارة المخاطر',
        'g6'=>'سياسة تضارب المصالح',
        'g7'=>'سياسة إفصاح (Disclosure Policy)',
        'g8'=>'سياسة علاقات المستثمرين',
        'g9'=>'توثيق محاضر اجتماعات مجلس الإدارة',
        'g10'=>'خطة التعاقب الإداري',
    ];
    $en = [
        'g1'=>'Separation between ownership and executive management',
        'g2'=>'Internal Audit function exists',
        'g3'=>'Audit Committee in place',
        'g5'=>'Risk Management Policy',
        'g6'=>'Conflict of Interest Policy',
        'g7'=>'Disclosure Policy',
        'g8'=>'Investor Relations Policy',
        'g9'=>'Board meeting minutes documented',
        'g10'=>'Management Succession Plan',
    ];
    $qs = $isAr ? $ar : $en;
    $align = $isAr ? 'right' : 'left';
    $html = '';
    $i = 0;
    foreach ($qs as $k => $label) {
        $val = ($govData[$k] ?? 'no');
        $bg = $i % 2 === 0 ? '#F8FAFD' : '#FFFFFF';
        $display = $val === 'yes' ? ($isAr ? '✅ نعم' : '✅ Yes') : ($isAr ? '❌ لا' : '❌ No');
        $color = $val === 'yes' ? '#27AE60' : '#C0392B';
        $html .= "<tr><td style='padding:9px 14px;font-size:13px;color:#1A2B4A;border-bottom:1px solid #EEF4FF;text-align:{$align};background:{$bg};'>".esc_html($label)."</td><td style='padding:9px 14px;font-size:13px;font-weight:700;color:{$color};border-bottom:1px solid #EEF4FF;text-align:center;background:{$bg};'>{$display}</td></tr>";
        $i++;
    }
    $im = intval($govData['independentMembers'] ?? 0);
    $bg = $i % 2 === 0 ? '#F8FAFD' : '#FFFFFF';
    $lbl = $isAr ? 'عدد الأعضاء المستقلين' : 'Independent Board Members';
    $html .= "<tr><td style='padding:9px 14px;font-size:13px;color:#1A2B4A;text-align:{$align};background:{$bg};'>{$lbl}</td><td style='padding:9px 14px;font-size:13px;font-weight:700;color:#1A3A6B;text-align:center;background:{$bg};'>{$im}</td></tr>";
    return $html;
}

function epro_ipo_rows($isAr, $ipoData) {
    $ar = [
        'ipo1'=>'قوائم مالية مدققة لسنتين أو أكثر',
        'ipo2'=>'تطبيق معايير IFRS للمحاسبة',
        'ipo3'=>'القوائم المالية موحدة (Consolidated)',
        'ipo4'=>'أعضاء مستقلون في مجلس الإدارة',
        'ipo5'=>'لجنة مراجعة (Audit Committee)',
        'ipo6'=>'سياسة إفصاح معتمدة',
        'ipo7'=>'سياسة علاقات مستثمرين',
        'ipo8'=>'التدفقات النقدية التشغيلية إيجابية',
    ];
    $en = [
        'ipo1'=>'Audited financial statements for 2+ years',
        'ipo2'=>'IFRS accounting standards applied',
        'ipo3'=>'Financial statements consolidated',
        'ipo4'=>'Independent board members exist',
        'ipo5'=>'Audit Committee in place',
        'ipo6'=>'Approved Disclosure Policy',
        'ipo7'=>'Investor Relations Policy',
        'ipo8'=>'Operating cash flows positive',
    ];
    $qs = $isAr ? $ar : $en;
    $align = $isAr ? 'right' : 'left';
    $html = '';
    $i = 0;
    foreach ($qs as $k => $label) {
        $val = ($ipoData[$k] ?? 'no');
        $bg = $i % 2 === 0 ? '#F8FAFD' : '#FFFFFF';
        $display = $val === 'yes' ? ($isAr ? '✅ نعم' : '✅ Yes') : ($isAr ? '❌ لا' : '❌ No');
        $color = $val === 'yes' ? '#27AE60' : '#C0392B';
        $html .= "<tr><td style='padding:9px 14px;font-size:13px;color:#1A2B4A;border-bottom:1px solid #EEF4FF;text-align:{$align};background:{$bg};'>".esc_html($label)."</td><td style='padding:9px 14px;font-size:13px;font-weight:700;color:{$color};border-bottom:1px solid #EEF4FF;text-align:center;background:{$bg};'>{$display}</td></tr>";
        $i++;
    }
    return $html;
}

// ==============================
// HTML EMAIL BUILDER
// ==============================
function epro_build_email_html($lang, $data, $scores, $adminBanner=false) {
    $isAr = ($lang === 'ar');
    $dir  = $isAr ? 'rtl' : 'ltr';
    $align = $isAr ? 'right' : 'left';

    $ln  = esc_html($data['name']);
    $cn  = esc_html($data['company']);
    $em  = esc_html($data['email']);
    $ph  = esc_html($data['phone']);
    $sec = esc_html($data['sector']);
    $cou = esc_html($data['country']);
    $fy  = esc_html($data['foundedYear']);
    $emp = esc_html($data['employees']);
    $rev = esc_html($data['annualRevenue']);
    $goal= esc_html($data['assessmentGoal']);

    $fin = $scores['fin'];
    $gs  = $scores['gov'];
    $is  = $scores['ipo'];
    $ov  = $scores['overall'];
    $oc  = esc_html($scores['class']);
    $isB = $scores['blocked'];
    $bl  = $scores['blockers'];
    $revC= floatval($scores['revCurrent']);
    $revP= floatval($scores['revPrev']);
    $net = floatval($scores['net']);
    $gross=floatval($scores['gross']);
    $assets=floatval($scores['assets']);
    $eq  = floatval($scores['equity']);
    $curA= floatval($scores['curA']);
    $curL= floatval($scores['curL']);
    $dbt = floatval($scores['debt']);

    $scoreColor = $ov>=80?'#27AE60':($ov>=65?'#D4AC0D':($ov>=50?'#E67E22':'#E74C3C'));

    $L = $isAr ? [
        'greeting'    => "مرحباً $ln،",
        'thanks'      => 'شكراً لاستخدامك أداة تقييم جاهزية الطرح العام من EPRO.',
        'report_for'  => 'تقرير تقييم جاهزية الطرح العام لـ',
        'overall'     => 'النتيجة الكلية',
        'out_of'      => 'من 100',
        'classification'=> 'التصنيف',
        'client_info' => 'بيانات مقدّم الطلب',
        'name'        => 'الاسم',
        'email'       => 'البريد الإلكتروني',
        'phone'       => 'الهاتف',
        'company'     => 'الشركة',
        'sector'      => 'القطاع',
        'country'     => 'الدولة',
        'founded'     => 'سنة التأسيس',
        'employees'   => 'عدد الموظفين',
        'revenue'     => 'الإيرادات السنوية',
        'goal'        => 'هدف التقييم',
        'scores_title'=> 'ملخص النتائج',
        'fin_health'  => 'الصحة المالية',
        'governance'  => 'الحوكمة',
        'ipo_ready'   => 'جاهزية الطرح',
        'blocked_lbl' => '⛔ معطّل',
        'ratios_title'=> 'تفاصيل النسب المالية',
        'indicator'   => 'المؤشر',
        'value'       => 'القيمة',
        'rating'      => 'التصنيف',
        'net_margin'  => 'هامش صافي الربح',
        'roe'         => 'العائد على حقوق الملكية (ROE)',
        'roa'         => 'العائد على الأصول (ROA)',
        'gross_margin'=> 'هامش الربح الإجمالي',
        'rev_growth'  => 'معدل نمو الإيرادات',
        'curr_ratio'  => 'نسبة السيولة الجارية',
        'cash_ratio'  => 'نسبة النقد',
        'dte'         => 'الدين إلى حقوق الملكية',
        'dta'         => 'الدين إلى الأصول',
        'eta'         => 'حقوق الملكية إلى الأصول',
        'blockers_title'=> '⛔ معوقات الطرح العام',
        'footer'      => 'للاستفسار أو الحصول على استشارة متخصصة',
    ] : [
        'greeting'    => "Dear $ln,",
        'thanks'      => 'Thank you for using the EPRO IPO Readiness Assessment Tool.',
        'report_for'  => 'IPO Readiness Assessment Report for',
        'overall'     => 'Overall Score',
        'out_of'      => 'out of 100',
        'classification'=> 'Classification',
        'client_info' => 'Applicant Information',
        'name'        => 'Name',
        'email'       => 'Email',
        'phone'       => 'Phone',
        'company'     => 'Company',
        'sector'      => 'Sector',
        'country'     => 'Country',
        'founded'     => 'Year Founded',
        'employees'   => 'Employees',
        'revenue'     => 'Annual Revenue',
        'goal'        => 'Assessment Goal',
        'scores_title'=> 'Results Summary',
        'fin_health'  => 'Financial Health',
        'governance'  => 'Governance',
        'ipo_ready'   => 'IPO Readiness',
        'blocked_lbl' => '⛔ Blocked',
        'ratios_title'=> 'Financial Ratios Detail',
        'indicator'   => 'Indicator',
        'value'       => 'Value',
        'rating'      => 'Rating',
        'net_margin'  => 'Net Profit Margin',
        'roe'         => 'Return on Equity (ROE)',
        'roa'         => 'Return on Assets (ROA)',
        'gross_margin'=> 'Gross Profit Margin',
        'rev_growth'  => 'Revenue Growth Rate',
        'curr_ratio'  => 'Current Ratio',
        'cash_ratio'  => 'Cash Ratio',
        'dte'         => 'Debt-to-Equity',
        'dta'         => 'Debt-to-Assets',
        'eta'         => 'Equity-to-Assets',
        'blockers_title'=> '⛔ IPO Blockers',
        'footer'      => 'For inquiries or specialized advisory services',
    ];

    if(!function_exists('epro_pct')){function epro_pct($n,$d){ return $d>0?round(($n/$d)*100,1).'%':'—'; }}
    if(!function_exists('epro_rto')){function epro_rto($n,$d){ return $d>0?round($n/$d,2).'x':'—'; }}

    $ratioRows = [
        [$L['net_margin'],  epro_pct($net,$revC)],
        [$L['roe'],         $eq>0?round(($net/$eq)*100,1).'%':'—'],
        [$L['roa'],         epro_pct($net,$assets)],
        [$L['gross_margin'],epro_pct($gross,$revC)],
        [$L['rev_growth'],  $revP>0?round((($revC-$revP)/$revP)*100,1).'%':'—'],
        [$L['curr_ratio'],  epro_rto($curA,$curL)],
        [$L['cash_ratio'],  epro_rto(floatval($scores['cash']),$curL)],
        [$L['dte'],         $eq>0?round($dbt/$eq,2).'x':'—'],
        [$L['dta'],         epro_pct($dbt,$assets)],
        [$L['eta'],         epro_pct($eq,$assets)],
    ];

    $ratioRowsHtml = '';
    foreach($ratioRows as $i=>$row){
        $bg = $i%2===0?'#F8FAFD':'#FFFFFF';
        $ratioRowsHtml .= "
        <tr>
            <td style='padding:9px 14px;font-size:13px;color:#1A2B4A;border-bottom:1px solid #EEF4FF;text-align:{$align};background:{$bg};'>{$row[0]}</td>
            <td style='padding:9px 14px;font-size:13px;font-weight:700;color:#1A3A6B;border-bottom:1px solid #EEF4FF;text-align:center;background:{$bg};'>{$row[1]}</td>
        </tr>";
    }

    $blockersHtml = '';
    if($isB && count($bl)>0){
        $bItems = '';
        foreach($bl as $b) $bItems .= "<li style='padding:5px 0;font-size:13px;color:#A93226;'>⛔ ".esc_html($b)."</li>";
        $blockersHtml = "
        <tr><td colspan='2' style='padding:0;'>
        <table width='100%' cellpadding='0' cellspacing='0' style='background:#FEF0EE;border:1px solid #FADBD8;border-radius:8px;margin-top:16px;'>
            <tr><td style='padding:16px 18px;'>
                <div style='font-weight:800;color:#C0392B;font-size:14px;margin-bottom:8px;text-align:{$align};'>{$L['blockers_title']}</div>
                <ul style='margin:0;padding:0;list-style:none;text-align:{$align};'>{$bItems}</ul>
            </td></tr>
        </table>
        </td></tr>";
    }

    $ipoCell = $isB ? $L['blocked_lbl'] : $is.'/100';
    $ipoColor= $isB ? '#C0392B' : ($is>=80?'#27AE60':($is>=65?'#D4AC0D':'#E74C3C'));

    $html = '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body style="margin:0;padding:0;background:#F0F4F8;font-family:Arial,sans-serif;">
    <table width="100%" cellpadding="0" cellspacing="0" style="background:#F0F4F8;padding:30px 0;">
    <tr><td align="center">
    <table width="600" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:14px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,.10);" dir="'.$dir.'">

    '.($adminBanner ? '
    <tr><td style="background:#1A3A6B;padding:10px 32px;text-align:'.$align.';">
        <span style="color:#C9A84C;font-size:12px;font-weight:700;">🔔 '.($isAr?'تقييم جديد وصل من':'New assessment received from').' </span>
        <span style="color:#ffffff;font-size:12px;font-weight:900;">'.$cn.'</span>
    </td></tr>
    ' : '').'

    <!-- HEADER -->
    <tr><td style="background:linear-gradient(135deg,#1A3A6B,#2D6BC4);padding:36px 32px;text-align:center;">
        <div style="color:rgba(255,255,255,.7);font-size:12px;letter-spacing:2px;margin-bottom:8px;font-weight:600;">EPRO ASSESSMENT TOOL</div>
        <div style="color:#C9A84C;font-size:11px;letter-spacing:1px;margin-bottom:16px;">eprome.com</div>
        <div style="color:#ffffff;font-size:22px;font-weight:900;margin-bottom:18px;">'.$L['report_for'].' '.$cn.'</div>
        <table cellpadding="0" cellspacing="0" align="center"><tr><td style="width:100px;height:100px;border-radius:50%;border:4px solid rgba(201,168,76,.6);background:rgba(255,255,255,.12);text-align:center;vertical-align:middle;">
            <div style="font-size:34px;font-weight:900;color:#ffffff;line-height:1;">'.$ov.'</div>
            <div style="font-size:11px;color:rgba(255,255,255,.7);">'.$L['out_of'].'</div>
        </td></tr></table>
        <div style="margin-top:14px;display:inline-block;padding:8px 24px;border-radius:24px;border:2px solid #C9A84C;color:#C9A84C;font-size:15px;font-weight:800;">'.$oc.'</div>
    </td></tr>

    <!-- GREETING -->
    <tr><td style="padding:28px 32px 0;text-align:'.$align.';">
        <p style="font-size:15px;color:#1A2B4A;margin:0 0 6px;">'.$L['greeting'].'</p>
        <p style="font-size:13px;color:#6B7FA3;margin:0;">'.$L['thanks'].'</p>
    </td></tr>

    <!-- CLIENT INFO -->
    <tr><td style="padding:20px 32px 0;">
        <div style="font-size:14px;font-weight:800;color:#1A3A6B;margin-bottom:12px;text-align:'.$align.';">'.$L['client_info'].'</div>
        <table width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #D0DCF0;border-radius:10px;overflow:hidden;">
            <tr style="background:#EEF4FF;">
                <td style="padding:9px 14px;font-size:12px;font-weight:700;color:#1A3A6B;width:40%;text-align:'.$align.';">'.$L['name'].'</td>
                <td style="padding:9px 14px;font-size:13px;color:#1A2B4A;text-align:'.$align.';">'.$ln.'</td>
            </tr>
            <tr><td style="padding:9px 14px;font-size:12px;font-weight:700;color:#1A3A6B;background:#F8FAFD;text-align:'.$align.';">'.$L['email'].'</td>
                <td style="padding:9px 14px;font-size:13px;color:#1A2B4A;background:#F8FAFD;text-align:'.$align.';">'.$em.'</td></tr>
            <tr style="background:#EEF4FF;"><td style="padding:9px 14px;font-size:12px;font-weight:700;color:#1A3A6B;text-align:'.$align.';">'.$L['phone'].'</td>
                <td style="padding:9px 14px;font-size:13px;color:#1A2B4A;text-align:'.$align.';">'.$ph.'</td></tr>
            <tr><td style="padding:9px 14px;font-size:12px;font-weight:700;color:#1A3A6B;background:#F8FAFD;text-align:'.$align.';">'.$L['company'].'</td>
                <td style="padding:9px 14px;font-size:13px;color:#1A2B4A;background:#F8FAFD;text-align:'.$align.';">'.$cn.'</td></tr>
            <tr style="background:#EEF4FF;"><td style="padding:9px 14px;font-size:12px;font-weight:700;color:#1A3A6B;text-align:'.$align.';">'.$L['sector'].'</td>
                <td style="padding:9px 14px;font-size:13px;color:#1A2B4A;text-align:'.$align.';">'.$sec.'</td></tr>
            <tr><td style="padding:9px 14px;font-size:12px;font-weight:700;color:#1A3A6B;background:#F8FAFD;text-align:'.$align.';">'.$L['country'].'</td>
                <td style="padding:9px 14px;font-size:13px;color:#1A2B4A;background:#F8FAFD;text-align:'.$align.';">'.$cou.'</td></tr>
            <tr style="background:#EEF4FF;"><td style="padding:9px 14px;font-size:12px;font-weight:700;color:#1A3A6B;text-align:'.$align.';">'.$L['founded'].'</td>
                <td style="padding:9px 14px;font-size:13px;color:#1A2B4A;text-align:'.$align.';">'.$fy.'</td></tr>
            <tr><td style="padding:9px 14px;font-size:12px;font-weight:700;color:#1A3A6B;background:#F8FAFD;text-align:'.$align.';">'.$L['employees'].'</td>
                <td style="padding:9px 14px;font-size:13px;color:#1A2B4A;background:#F8FAFD;text-align:'.$align.';">'.$emp.'</td></tr>
            <tr style="background:#EEF4FF;"><td style="padding:9px 14px;font-size:12px;font-weight:700;color:#1A3A6B;text-align:'.$align.';">'.$L['goal'].'</td>
                <td style="padding:9px 14px;font-size:13px;color:#1A2B4A;text-align:'.$align.';">'.$goal.'</td></tr>
        </table>
    </td></tr>

    <!-- SCORES -->
    <tr><td style="padding:20px 32px 0;">
        <div style="font-size:14px;font-weight:800;color:#1A3A6B;margin-bottom:12px;text-align:'.$align.';">'.$L['scores_title'].'</div>
        <table width="100%" cellpadding="0" cellspacing="0">
        <tr>
            <td width="33%" style="padding:0 4px 0 0;">
                <table width="100%" style="background:#F4F7FB;border:1.5px solid #D0DCF0;border-radius:10px;"><tr><td style="padding:16px;text-align:center;">
                    <div style="font-size:11px;color:#6B7FA3;margin-bottom:6px;">'.$L['fin_health'].'</div>
                    <div style="font-size:28px;font-weight:900;color:'.($fin>=80?'#27AE60':($fin>=65?'#D4AC0D':($fin>=50?'#E67E22':'#E74C3C'))).'">'.$fin.'</div>
                </td></tr></table>
            </td>
            <td width="33%" style="padding:0 4px;">
                <table width="100%" style="background:#F4F7FB;border:1.5px solid #D0DCF0;border-radius:10px;"><tr><td style="padding:16px;text-align:center;">
                    <div style="font-size:11px;color:#6B7FA3;margin-bottom:6px;">'.$L['governance'].'</div>
                    <div style="font-size:28px;font-weight:900;color:'.($gs>=80?'#27AE60':($gs>=65?'#D4AC0D':($gs>=50?'#E67E22':'#E74C3C'))).'">'.$gs.'</div>
                </td></tr></table>
            </td>
            <td width="33%" style="padding:0 0 0 4px;">
                <table width="100%" style="background:#F4F7FB;border:1.5px solid #D0DCF0;border-radius:10px;"><tr><td style="padding:16px;text-align:center;">
                    <div style="font-size:11px;color:#6B7FA3;margin-bottom:6px;">'.$L['ipo_ready'].'</div>
                    <div style="font-size:28px;font-weight:900;color:'.$ipoColor.'">'.$ipoCell.'</div>
                </td></tr></table>
            </td>
        </tr>
        </table>
    </td></tr>

    '.($blockersHtml ? '<tr><td style="padding:16px 32px 0;">'.$blockersHtml.'</td></tr>' : '').'

    <!-- RATIOS -->
    <tr><td style="padding:20px 32px 0;">
        <div style="font-size:14px;font-weight:800;color:#1A3A6B;margin-bottom:12px;text-align:'.$align.';">'.$L['ratios_title'].'</div>
        <table width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #D0DCF0;border-radius:10px;overflow:hidden;">
            <tr style="background:#EEF4FF;">
                <th style="padding:10px 14px;font-size:12px;color:#1A3A6B;font-weight:700;text-align:'.$align.';">'.$L['indicator'].'</th>
                <th style="padding:10px 14px;font-size:12px;color:#1A3A6B;font-weight:700;text-align:center;">'.$L['value'].'</th>
            </tr>
            '.$ratioRowsHtml.'
        </table>
    </td></tr>

    '.($adminBanner ? '

    <!-- FINANCIAL RAW DATA -->
    <tr><td style="padding:20px 32px 0;">
        <div style="font-size:14px;font-weight:800;color:#1A3A6B;margin-bottom:12px;text-align:'.$align.';">'.($isAr?'📊 البيانات المالية المدخلة':'📊 Entered Financial Data').'</div>
        <table width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #D0DCF0;border-radius:10px;overflow:hidden;">
        <tr style="background:#EEF4FF;">
            <th style="padding:10px 14px;font-size:12px;color:#1A3A6B;font-weight:700;text-align:'.$align.';">'.($isAr?'البند':'Item').'</th>
            <th style="padding:10px 14px;font-size:12px;color:#1A3A6B;font-weight:700;text-align:center;">'.($isAr?'القيمة':'Value').'</th>
        </tr>
        '.epro_fin_rows($isAr,$scores).'
        </table>
    </td></tr>

    <!-- GOVERNANCE ANSWERS -->
    <tr><td style="padding:16px 32px 0;">
        <div style="font-size:14px;font-weight:800;color:#1A3A6B;margin-bottom:12px;text-align:'.$align.';">'.($isAr?'⚖️ إجابات الحوكمة':'⚖️ Governance Answers').'</div>
        <table width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #D0DCF0;border-radius:10px;overflow:hidden;">
        <tr style="background:#EEF4FF;">
            <th style="padding:10px 14px;font-size:12px;color:#1A3A6B;font-weight:700;text-align:'.$align.';">'.($isAr?'السؤال':'Question').'</th>
            <th style="padding:10px 14px;font-size:12px;color:#1A3A6B;font-weight:700;text-align:center;">'.($isAr?'الإجابة':'Answer').'</th>
        </tr>
        '.epro_gov_rows($isAr,$scores['govData']??[]).'
        </table>
    </td></tr>

    <!-- IPO ANSWERS -->
    <tr><td style="padding:16px 32px 0;">
        <div style="font-size:14px;font-weight:800;color:#1A3A6B;margin-bottom:12px;text-align:'.$align.';">'.($isAr?'🚀 إجابات جاهزية الطرح':'🚀 IPO Readiness Answers').'</div>
        <table width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #D0DCF0;border-radius:10px;overflow:hidden;">
        <tr style="background:#EEF4FF;">
            <th style="padding:10px 14px;font-size:12px;color:#1A3A6B;font-weight:700;text-align:'.$align.';">'.($isAr?'السؤال':'Question').'</th>
            <th style="padding:10px 14px;font-size:12px;color:#1A3A6B;font-weight:700;text-align:center;">'.($isAr?'الإجابة':'Answer').'</th>
        </tr>
        '.epro_ipo_rows($isAr,$scores['ipoData']??[]).'
        </table>
    </td></tr>

    ' : '').'

    <!-- FOOTER -->
    <tr><td style="padding:28px 32px;text-align:center;border-top:3px solid #C9A84C;margin-top:20px;background:#1A3A6B;">
        <div style="color:#C9A84C;font-size:16px;font-weight:900;letter-spacing:2px;margin-bottom:6px;">EPRO</div>
        <p style="font-size:12px;color:rgba(255,255,255,0.7);margin:0 0 6px;">'.$L['footer'].'</p>
        <p style="font-size:13px;font-weight:700;color:#ffffff;margin:0;">info@eprome.com &nbsp;|&nbsp; www.eprome.com</p>
        <p style="font-size:11px;color:rgba(255,255,255,0.5);margin:8px 0 0;">Building No. 3630, 2nd Floor, Al Urubah St, Al Wurud District, Riyadh 12252, KSA</p>
    </td></tr>

    </table>
    </td></tr></table>
    </body></html>';

    return $html;
}

function epro_handle_submit() {
    check_ajax_referer('epro_nonce', 'nonce');
    global $wpdb;
    $table = $wpdb->prefix . 'epro_assessments';
    $data = $_POST['data'] ?? [];

    $rev=floatval($data['revCurrent']??0); $revPrev=floatval($data['revPrev']??0);
    $net=floatval($data['netProfit']??0); $gross=floatval($data['grossProfit']??0);
    $assets=floatval($data['totalAssets']??0); $eq=floatval($data['equity']??0);
    $curA=floatval($data['currentAssets']??0); $curL=floatval($data['currentLiabilities']??0);
    $cash=floatval($data['cash']??0); $debt=floatval($data['totalDebt']??0);

    $ratios=['netMargin'=>$rev>0?($net/$rev)*100:0,'roe'=>$eq>0?($net/$eq)*100:-999,'roa'=>$assets>0?($net/$assets)*100:0,'grossMargin'=>$rev>0?($gross/$rev)*100:0,'revGrowth'=>$revPrev>0?(($rev-$revPrev)/$revPrev)*100:0,'currentRatio'=>$curL>0?$curA/$curL:0,'cashRatio'=>$curL>0?$cash/$curL:0,'debtToEquity'=>$eq>0?$debt/$eq:999,'debtToAsset'=>$assets>0?($debt/$assets)*100:0,'equityToAsset'=>$assets>0?($eq/$assets)*100:0];

    if(!function_exists('ec')){function ec($k,$v,$eqv){
        if($k==='roe'&&$eqv<=0)return['score'=>20,'emoji'=>'🔴'];
        $m=['netMargin'=>[15,5],'roe'=>[20,10],'roa'=>[10,5],'grossMargin'=>[40,25],'revGrowth'=>[10,0]];
        if(isset($m[$k])){if($v>$m[$k][0])return['score'=>100,'emoji'=>'🟢'];if($v>=$m[$k][1])return['score'=>60,'emoji'=>'🟡'];return['score'=>20,'emoji'=>'🔴'];}
        if($k==='currentRatio'){if($v>=1.5&&$v<=3)return['score'=>100,'emoji'=>'🟢'];if($v>=1)return['score'=>60,'emoji'=>'🟡'];return['score'=>20,'emoji'=>'🔴'];}
        if($k==='cashRatio'){if($v>0.5)return['score'=>100,'emoji'=>'🟢'];if($v>=0.2)return['score'=>60,'emoji'=>'🟡'];return['score'=>20,'emoji'=>'🔴'];}
        if($k==='debtToEquity'){if($v<1)return['score'=>100,'emoji'=>'🟢'];if($v<=2)return['score'=>60,'emoji'=>'🟡'];return['score'=>20,'emoji'=>'🔴'];}
        if($k==='debtToAsset'){if($v<40)return['score'=>100,'emoji'=>'🟢'];if($v<=60)return['score'=>60,'emoji'=>'🟡'];return['score'=>20,'emoji'=>'🔴'];}
        if($k==='equityToAsset'){if($v>40)return['score'=>100,'emoji'=>'🟢'];if($v>=20)return['score'=>60,'emoji'=>'🟡'];return['score'=>20,'emoji'=>'🔴'];}
        return['score'=>20,'emoji'=>'🔴'];
    }}
    $cls=[];foreach($ratios as $k=>$v)$cls[$k]=ec($k,$v,$eq);
    $w=['netMargin'=>0.10,'roe'=>0.10,'roa'=>0.08,'grossMargin'=>0.07,'revGrowth'=>0.05,'currentRatio'=>0.15,'cashRatio'=>0.10,'debtToEquity'=>0.25,'debtToAsset'=>0.05,'equityToAsset'=>0.05];
    $fin=0;foreach($w as $k=>$wt)$fin+=$cls[$k]['score']*$wt;
    $co=($eq<=0||$ratios['debtToEquity']>2.5||$ratios['currentRatio']<0.8||$ratios['revGrowth']<0);
    if($co)$fin=min($fin,45);

    $gi=['g1'=>0.10,'g2'=>0.10,'g3'=>0.15,'g5'=>0.10,'g6'=>0.10,'g7'=>0.10,'g8'=>0.10,'g9'=>0.05,'g10'=>0.05];
    $gov=$data['governance']??[]; $gs=0;
    foreach($gi as $k=>$wt)$gs+=(($gov[$k]??'no')==='yes'?100:20)*$wt;
    $im=intval($gov['independentMembers']??0);$gs+=($im>=3?100:($im>=1?60:20))*0.15;

    $ipo=$data['ipo']??[];$bl=[];
    if(($ipo['ipo1']??'no')==='no')$bl[]='أقل من سنتين قوائم مالية مدققة';
    if(($ipo['ipo2']??'no')==='no')$bl[]='عدم تطبيق معايير IFRS';
    if(($ipo['ipo3']??'no')==='no')$bl[]='القوائم المالية غير موحدة';
    if(($ipo['ipo4']??'no')==='no')$bl[]='عدم وجود أعضاء مستقلين';
    if(($ipo['ipo5']??'no')==='no')$bl[]='عدم وجود لجنة مراجعة';
    if(($ipo['ipo6']??'no')==='no')$bl[]='عدم وجود سياسة إفصاح';
    if(($ipo['ipo7']??'no')==='no')$bl[]='عدم وجود سياسة علاقات مستثمرين';
    if($eq<=0)$bl[]='حقوق ملكية سالبة';
    if($ratios['debtToEquity']>2)$bl[]='نسبة الدين إلى حقوق الملكية > 2';
    if(($ipo['ipo8']??'no')==='no')$bl[]='تدفقات نقدية تشغيلية سالبة';

    $isB=count($bl)>0;$is=$isB?0:((10-count($bl))/10)*100;
    $ov=($fin*0.40)+($gs*0.40)+($is*0.20);
    $oc=$ov>=80?'ممتاز – جاهز للطرح':($ov>=65?'جيد – مع بعض التحسينات':($ov>=50?'متوسط – يحتاج إعادة هيكلة':'ضعيف – مخاطر عالية'));

    $wpdb->insert($table,['lead_name'=>sanitize_text_field($data['leadName']??''),'lead_email'=>sanitize_email($data['leadEmail']??''),'lead_phone'=>sanitize_text_field($data['leadPhone']??''),'company_name'=>sanitize_text_field($data['companyName']??''),'sector'=>sanitize_text_field($data['sector']??''),'country'=>sanitize_text_field($data['country']??''),'founded_year'=>intval($data['foundedYear']??0),'annual_revenue'=>floatval($data['annualRevenue']??0),'employees'=>intval($data['employees']??0),'assessment_goal'=>sanitize_text_field($data['assessmentGoal']??''),'rev_current'=>$rev,'rev_prev'=>$revPrev,'net_profit'=>$net,'gross_profit'=>$gross,'total_assets'=>$assets,'total_liabilities'=>floatval($data['totalLiabilities']??0),'equity'=>$eq,'current_assets'=>$curA,'current_liabilities'=>$curL,'cash'=>$cash,'total_debt'=>$debt,'governance_data'=>json_encode($gov),'ipo_data'=>json_encode($ipo),'results_json'=>json_encode(['ratios'=>$ratios,'classifications'=>$cls,'blockers'=>$bl,'criticalOverride'=>$co]),'financial_score'=>round($fin,2),'governance_score'=>round($gs,2),'ipo_score'=>round($is,2),'overall_score'=>round($ov,2),'ipo_blocked'=>$isB?1:0,'overall_class'=>$oc]);

    $ce=sanitize_email($data['leadEmail']??'');
    $cn=sanitize_text_field($data['companyName']??'');
    $ln=sanitize_text_field($data['leadName']??'');
    $emailData=['name'=>$ln,'company'=>$cn,'email'=>$ce,'phone'=>sanitize_text_field($data['leadPhone']??''),'sector'=>sanitize_text_field($data['sector']??''),'country'=>sanitize_text_field($data['country']??''),'foundedYear'=>intval($data['foundedYear']??0),'employees'=>intval($data['employees']??0),'annualRevenue'=>floatval($data['annualRevenue']??0),'assessmentGoal'=>sanitize_text_field($data['assessmentGoal']??'')];
    $emailScores=['fin'=>round($fin,1),'gov'=>round($gs,1),'ipo'=>round($is,1),'overall'=>round($ov,1),'class'=>$oc,'blocked'=>$isB,'blockers'=>$bl,'ratios'=>$ratios,'revCurrent'=>$rev,'revPrev'=>$revPrev,'net'=>$net,'gross'=>$gross,'assets'=>$assets,'equity'=>$eq,'curA'=>$curA,'curL'=>$curL,'debt'=>$debt,'cash'=>$cash,'govData'=>$gov,'ipoData'=>$ipo];
    $htmlEmail=epro_build_email_html('ar',$emailData,$emailScores);
    $hdrs=['Content-Type: text/html; charset=UTF-8','From: EPRO <info@eprome.com>'];
    $htmlEmailAdmin=epro_build_email_html('ar',$emailData,$emailScores,true);
    wp_mail('info@eprome.com',"تقييم جديد – {$cn} | EPRO",$htmlEmailAdmin,$hdrs);
    if($ce)wp_mail($ce,"نتائج تقييم جاهزية الطرح العام – {$cn} | EPRO",$htmlEmail,$hdrs);

    wp_send_json_success(['finScore'=>round($fin,1),'govScore'=>round($gs,1),'ipoScore'=>round($is,1),'overall'=>round($ov,1),'overallClass'=>$oc,'isBlocked'=>$isB,'blockers'=>$bl,'ratios'=>$ratios,'classifications'=>$cls,'criticalOverride'=>$co]);
}

function epro_export_csv() {
    if(!current_user_can('manage_options'))wp_die('Unauthorized');
    global $wpdb;$table=$wpdb->prefix.'epro_assessments';
    $rows=$wpdb->get_results($wpdb->prepare("SELECT * FROM `{$table}` ORDER BY created_at DESC LIMIT %d",1000),ARRAY_A);
    header('Content-Type: text/csv; charset=utf-8');header('Content-Disposition: attachment; filename=epro-'.date('Y-m-d').'.csv');
    $o=fopen('php://output','w');fprintf($o,chr(0xEF).chr(0xBB).chr(0xBF));
    fputcsv($o,['ID','التاريخ','الاسم','البريد','الهاتف','الشركة','القطاع','الدولة','الإيرادات','الموظفين','الهدف','المالية','الحوكمة','IPO','الكلية','التصنيف','معوّق']);
    foreach($rows as $r)fputcsv($o,[$r['id'],$r['created_at'],$r['lead_name'],$r['lead_email'],$r['lead_phone'],$r['company_name'],$r['sector'],$r['country'],$r['annual_revenue'],$r['employees'],$r['assessment_goal'],$r['financial_score'],$r['governance_score'],$r['ipo_score'],$r['overall_score'],$r['overall_class'],$r['ipo_blocked']?'نعم':'لا']);
    fclose($o);exit;
}

function epro_admin_menu(){add_menu_page('EPRO','📊 EPRO','manage_options','epro-dashboard','epro_admin_page','dashicons-chart-bar',30);}
function epro_admin_page(){
    if(!current_user_can('manage_options'))return;
    global $wpdb;$table=$wpdb->prefix.'epro_assessments';
    $total=$wpdb->get_var("SELECT COUNT(*) FROM `$table`");
    $blocked=$wpdb->get_var("SELECT COUNT(*) FROM `$table` WHERE ipo_blocked=1");
    $avg=$wpdb->get_var("SELECT AVG(overall_score) FROM `$table`");
    $rows=$wpdb->get_results($wpdb->prepare("SELECT * FROM `{$table}` ORDER BY created_at DESC LIMIT %d",50),ARRAY_A);
    $eu=admin_url('admin-post.php?action=epro_export_csv');
    ?>
    <style>
    .ew{font-family:'Cairo',sans-serif;direction:rtl;padding:20px;}
    .es{display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-bottom:28px;}
    .esb{background:#1e2a3a;border:1px solid #2d4060;border-radius:12px;padding:20px;text-align:center;color:#fff;}
    .esb .n{font-size:36px;font-weight:900;color:#C9A84C;}
    .esb .l{font-size:13px;color:#8892B0;margin-top:4px;}
    .et{background:#1e2a3a;border-radius:12px;overflow:hidden;border:1px solid #2d4060;}
    .et table{width:100%;border-collapse:collapse;}
    .et th{background:#162236;color:#C9A84C;padding:12px 14px;text-align:right;font-size:13px;font-weight:700;}
    .et td{padding:11px 14px;color:#ccd6f6;font-size:13px;border-bottom:1px solid #2d4060;}
    .et tr:hover td{background:rgba(201,168,76,.05);}
    .bx{display:inline-block;padding:3px 10px;border-radius:20px;font-size:12px;font-weight:600;}
    .bg{background:rgba(46,204,113,.15);color:#2ECC71;}.by{background:rgba(243,156,18,.15);color:#F39C12;}.bo{background:rgba(230,126,34,.15);color:#E67E22;}.br{background:rgba(231,76,60,.15);color:#E74C3C;}.bb{background:rgba(139,0,0,.2);color:#FF4444;}
    .eb{background:linear-gradient(135deg,#C9A84C,#9A7A2E);color:#0A1628;padding:10px 24px;border-radius:8px;text-decoration:none;font-weight:700;font-size:14px;display:inline-block;margin-bottom:20px;}
    </style>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;700;900&display=swap" rel="stylesheet">
    <div class="ew">
        <h1 style="color:#C9A84C;font-size:24px;margin-bottom:24px;">📊 لوحة تحكم EPRO – التقييمات</h1>
        <div class="es">
            <div class="esb"><div class="n"><?=intval($total)?></div><div class="l">إجمالي التقييمات</div></div>
            <div class="esb"><div class="n"><?=intval($blocked)?></div><div class="l">طرح معطّل ⛔</div></div>
            <div class="esb"><div class="n"><?=round(floatval($avg),1)?></div><div class="l">متوسط النتيجة</div></div>
            <div class="esb"><div class="n"><?=intval($total)-intval($blocked)?></div><div class="l">شركات محتملة ✅</div></div>
        </div>
        <a href="<?=esc_url($eu)?>" class="eb">⬇️ تصدير Excel / CSV</a>
        <div class="et"><table>
            <thead><tr><th>#</th><th>التاريخ</th><th>الشركة</th><th>الاسم</th><th>البريد</th><th>الهاتف</th><th>الكلية</th><th>مالية</th><th>حوكمة</th><th>IPO</th><th>التصنيف</th></tr></thead>
            <tbody>
            <?php if(empty($rows)):?><tr><td colspan="11" style="text-align:center;padding:30px;color:#8892B0;">لا توجد تقييمات بعد</td></tr>
            <?php else:foreach($rows as $r):$sc=floatval($r['overall_score']);$bc=$sc>=80?'bg':($sc>=65?'by':($sc>=50?'bo':'br'));if($r['ipo_blocked'])$bc='bb';?>
            <tr>
                <td><?=intval($r['id'])?></td><td><?=esc_html(date('Y/m/d',strtotime($r['created_at'])))?></td>
                <td><strong><?=esc_html($r['company_name'])?></strong></td><td><?=esc_html($r['lead_name'])?></td>
                <td><?=esc_html($r['lead_email'])?></td><td><?=esc_html($r['lead_phone'])?></td>
                <td><strong style="color:#C9A84C;font-size:16px;"><?=esc_html($r['overall_score'])?></strong></td>
                <td><?=esc_html($r['financial_score'])?></td><td><?=esc_html($r['governance_score'])?></td>
                <td><?=$r['ipo_blocked']?'⛔':esc_html($r['ipo_score'])?></td>
                <td><span class="bx <?=$bc?>"><?=esc_html($r['overall_class'])?></span></td>
            </tr>
            <?php endforeach;endif;?>
            </tbody>
        </table></div>
    </div>
    <?php
}

// ==============================
// ARABIC FORM
// ==============================
function epro_render_form(){
    $nonce=wp_create_nonce('epro_nonce');
    $ajax=admin_url('admin-ajax.php');
    ob_start();
    ?>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;600;700;900&family=Tajawal:wght@300;400;500;700&display=swap" rel="stylesheet">
    <style>
    /* ===================== EPRO FORM STYLES ===================== */
    #epro-app{--blue:#1A3A6B;--gold:#C9A84C;--blue-mid:#1E4D8C;--blue-l:#2D6BC4;--green:#27AE60;--gray:#F4F7FB;--border:#D0DCF0;--text:#1A2B4A;--muted:#6B7FA3;font-family:'Cairo',sans-serif;direction:rtl;color:var(--text);margin:48px auto 80px;max-width:860px;padding:0 20px;}
    #epro-app *{box-sizing:border-box;}
    .epro-hero{background:linear-gradient(135deg,#0D1F3C 0%,#1A3A6B 60%,#2D6BC4 100%);padding:48px 24px 36px;text-align:center;border-radius:16px;margin-bottom:6px;position:relative;overflow:hidden;}
    .epro-hero::before{content:'';position:absolute;top:-40%;left:50%;transform:translateX(-50%);width:600px;height:600px;background:radial-gradient(circle,rgba(201,168,76,.08),transparent 70%);pointer-events:none;}
    .epro-logo-bar{display:flex;align-items:center;justify-content:center;gap:10px;margin-bottom:20px;}
    .epro-logo-text{color:#C9A84C;font-size:22px;font-weight:900;letter-spacing:3px;}
    .epro-logo-sub{color:rgba(255,255,255,.5);font-size:10px;letter-spacing:1px;margin-top:2px;}
    .epro-badge{display:inline-block;background:rgba(201,168,76,.15);border:1px solid rgba(201,168,76,.4);color:#C9A84C;padding:5px 18px;border-radius:20px;font-size:12px;letter-spacing:1px;margin-bottom:14px;font-family:'Tajawal',sans-serif;}
    .epro-hero h2{font-size:clamp(22px,4vw,36px);font-weight:900;color:#fff;margin:0 0 10px;}
    .epro-hero h2 span{color:#C9A84C;}
    .epro-hero p{font-size:14px;color:rgba(255,255,255,.75);font-family:'Tajawal',sans-serif;max-width:620px;margin:0 auto;line-height:1.8;}
    .epro-divider{height:3px;background:linear-gradient(90deg,transparent,#C9A84C,transparent);margin:0;}
    .epro-intro{background:#EEF4FF;border:1px solid #C0D4F5;border-top:4px solid var(--gold);border-radius:14px;padding:26px 30px;margin-bottom:6px;}
    .epro-intro p{font-size:14px;color:#2A3F6B;font-family:'Tajawal',sans-serif;line-height:1.9;margin:0;}
    .epro-steps-wrap{background:#fff;border:1px solid var(--border);border-radius:14px;padding:20px 24px;margin-bottom:6px;}
    .epro-steps{display:flex;justify-content:center;gap:0;max-width:640px;margin:0 auto;flex-direction:row-reverse;}
    .epro-step{flex:1;display:flex;flex-direction:column;align-items:center;position:relative;}
    .epro-step:not(:last-child)::after{content:'';position:absolute;top:15px;right:0;width:100%;height:2px;background:#D0DCF0;z-index:0;}
    .epro-step.done:not(:last-child)::after{background:var(--gold);}
    .epro-snum{width:30px;height:30px;border-radius:50%;border:2px solid #D0DCF0;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:700;color:var(--muted);background:#fff;position:relative;z-index:1;transition:all .3s;}
    .epro-step.active .epro-snum{border-color:var(--gold);background:var(--gold);color:#fff;}
    .epro-step.done .epro-snum{border-color:var(--gold);background:var(--blue);color:#C9A84C;}
    .epro-slbl{font-size:10px;color:var(--muted);margin-top:5px;font-family:'Tajawal',sans-serif;text-align:center;}
    .epro-step.active .epro-slbl{color:var(--gold);font-weight:700;}
    .epro-step.done .epro-slbl{color:var(--blue);}
    .epro-card{background:#fff;border:1px solid var(--border);border-radius:14px;padding:36px;margin-bottom:6px;display:none;animation:eproF .35s ease;box-shadow:0 2px 16px rgba(26,58,107,.06);}
    .epro-card.active{display:block;}
    @keyframes eproF{from{opacity:0;transform:translateY(14px)}to{opacity:1;transform:translateY(0)}}
    .epro-ct{font-size:18px;font-weight:800;color:var(--blue);margin-bottom:4px;display:flex;align-items:center;gap:9px;}
    .etag{display:inline-block;background:#EEF4FF;color:var(--blue-l);padding:2px 10px;border-radius:6px;font-size:11px;font-weight:600;font-family:'Tajawal',sans-serif;}
    .epro-cs{font-size:13px;color:var(--muted);margin-bottom:26px;font-family:'Tajawal',sans-serif;line-height:1.7;}
    .epro-sl{font-size:12px;font-weight:700;color:var(--blue);background:linear-gradient(90deg,#EEF4FF,transparent);padding:6px 14px;border-right:3px solid var(--gold);border-radius:4px;display:inline-block;margin-bottom:16px;font-family:'Tajawal',sans-serif;}
    hr.epro-hr{border:none;border-top:1px solid var(--border);margin:22px 0;}
    .epro-row{display:grid;grid-template-columns:1fr 1fr;gap:18px;margin-bottom:18px;}
    .epro-row.one{grid-template-columns:1fr;}
    .epro-row.three{grid-template-columns:1fr 1fr 1fr;}
    .epro-fg{display:flex;flex-direction:column;gap:6px;}
    .epro-fg label{font-size:13px;font-weight:700;color:var(--text);font-family:'Tajawal',sans-serif;}
    .epro-fg label .req{color:#E74C3C;}
    .epro-fg input,.epro-fg select{background:#F8FAFD;border:1.5px solid var(--border);border-radius:9px;padding:12px 14px;color:var(--text);font-family:'Cairo',sans-serif;font-size:13px;outline:none;width:100%;direction:rtl;transition:all .2s;}
    .epro-fg input:focus,.epro-fg select:focus{border-color:var(--gold);background:#fff;box-shadow:0 0 0 3px rgba(201,168,76,.12);}
    .epro-fg input::placeholder{color:#A0AABF;}
    .epro-tog{display:flex;background:#F8FAFD;border:1.5px solid var(--border);border-radius:9px;overflow:hidden;}
    .epro-tog input[type="radio"]{display:none;}
    .epro-tog label{flex:1;text-align:center;padding:10px;cursor:pointer;font-size:13px;font-weight:600;color:var(--muted);font-family:'Tajawal',sans-serif;transition:all .2s;}
    .epro-tog input[type="radio"]:checked + .ylbl{background:#E8F8F0;color:#27AE60;}
    .epro-tog input[type="radio"]:checked + .nlbl{background:#FEF0EE;color:#E74C3C;}
    .epro-btns{display:flex;gap:12px;margin-top:28px;justify-content:space-between;flex-direction:row-reverse;}
    .epro-btn{padding:13px 28px;border-radius:10px;font-family:'Cairo',sans-serif;font-size:14px;font-weight:700;cursor:pointer;border:none;transition:all .3s;}
    .epro-bp{background:linear-gradient(135deg,#1A3A6B,#2D6BC4);color:#fff;}
    .epro-bp:hover{transform:translateY(-2px);box-shadow:0 6px 20px rgba(26,58,107,.3);}
    .epro-bp:disabled{opacity:.6;cursor:wait;}
    .epro-bs{background:#F4F7FB;border:1.5px solid var(--border);color:var(--blue);}
    .epro-bs:hover{background:#E8EFFE;}
    .epro-msg{padding:14px 18px;border-radius:9px;font-size:13px;margin-top:12px;display:none;font-family:'Tajawal',sans-serif;}
    .epro-msg.err{background:#FEF0EE;border:1px solid #FADBD8;color:#C0392B;display:block;}
    /* ===================== RESULTS STYLES ===================== */
    .epro-results{display:none;animation:eproF .5s ease;}
    .epro-results.active{display:block;}
    /* Report Header */
    .epro-rh{background:linear-gradient(135deg,#0D1F3C,#1A3A6B,#2D6BC4);border-radius:16px;padding:0;margin-bottom:14px;color:#fff;overflow:hidden;}
    .epro-rh-top{padding:8px 24px;background:rgba(201,168,76,.15);border-bottom:1px solid rgba(201,168,76,.3);display:flex;align-items:center;justify-content:space-between;}
    .epro-rh-logo{color:#C9A84C;font-size:16px;font-weight:900;letter-spacing:2px;}
    .epro-rh-url{color:rgba(255,255,255,.4);font-size:11px;}
    .epro-rh-body{padding:36px;text-align:center;}
    .epro-rh .cn{font-size:13px;opacity:.6;font-family:'Tajawal',sans-serif;margin-bottom:8px;letter-spacing:1px;}
    .epro-rh .company-title{font-size:24px;font-weight:900;color:#fff;margin-bottom:24px;}
    /* Score Ring */
    .epro-score-ring{width:120px;height:120px;border-radius:50%;border:4px solid rgba(201,168,76,.5);display:flex;flex-direction:column;align-items:center;justify-content:center;margin:0 auto 20px;background:rgba(255,255,255,.08);box-shadow:0 0 30px rgba(201,168,76,.2);}
    .epro-score-ring .sn{font-size:40px;font-weight:900;color:#fff;line-height:1;}
    .epro-score-ring .sl{font-size:11px;color:rgba(255,255,255,.6);}
    .epro-status-pill{display:inline-block;padding:9px 26px;border-radius:50px;font-size:15px;font-weight:800;margin-top:4px;border:2px solid rgba(255,255,255,.3);}
    /* Score Cards */
    .epro-scores-row{display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin-bottom:14px;}
    .epro-sc-card{background:#fff;border:1.5px solid var(--border);border-radius:12px;padding:20px 16px;text-align:center;box-shadow:0 2px 8px rgba(26,58,107,.05);}
    .epro-sc-card .sc-label{font-size:12px;color:var(--muted);margin-bottom:8px;font-family:'Tajawal',sans-serif;}
    .epro-sc-card .sc-val{font-size:28px;font-weight:900;line-height:1;margin-bottom:8px;}
    .epro-sc-card .sc-bar{height:6px;background:#EEF4FF;border-radius:10px;overflow:hidden;}
    .epro-sc-card .sc-bar-fill{height:100%;border-radius:10px;transition:width 1.2s ease;}
    /* Section cards */
    .epro-section{background:#fff;border:1px solid var(--border);border-radius:12px;margin-bottom:12px;overflow:hidden;box-shadow:0 2px 8px rgba(26,58,107,.04);}
    .epro-section-head{background:linear-gradient(90deg,#0D1F3C,#1A3A6B);padding:14px 20px;display:flex;align-items:center;gap:10px;}
    .epro-section-head .sec-ico{font-size:18px;}
    .epro-section-head .sec-title{color:#C9A84C;font-size:15px;font-weight:800;letter-spacing:.5px;}
    .epro-section-body{padding:0;}
    /* Table */
    .epro-rt{width:100%;border-collapse:collapse;}
    .epro-rt th{background:#EEF4FF;padding:11px 16px;text-align:right;font-size:12px;color:var(--blue);font-weight:700;border-bottom:2px solid var(--border);}
    .epro-rt td{padding:11px 16px;font-size:13px;color:var(--text);border-bottom:1px solid #F0F4FA;font-family:'Tajawal',sans-serif;}
    .epro-rt tr:last-child td{border-bottom:none;}
    .epro-rt tr:nth-child(even) td{background:#FAFBFD;}
    /* Blockers */
    .epro-blockers{background:#FEF0EE;border:1px solid #FADBD8;border-right:4px solid #C0392B;border-radius:12px;padding:20px;margin-bottom:12px;}
    .epro-blockers h4{color:#C0392B;font-size:15px;margin-bottom:12px;font-weight:800;display:flex;align-items:center;gap:8px;}
    .epro-blockers ul{list-style:none;margin:0;padding:0;}
    .epro-blockers ul li{padding:6px 0;font-size:13px;color:#A93226;font-family:'Tajawal',sans-serif;display:flex;align-items:center;gap:8px;border-bottom:1px solid rgba(192,57,43,.1);}
    .epro-blockers ul li:last-child{border-bottom:none;}
    .epro-blockers ul li::before{content:'✕';width:18px;height:18px;background:#C0392B;color:#fff;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:10px;flex-shrink:0;}
    /* Email Notice */
    .epro-email-notice{background:linear-gradient(90deg,#EEF4FF,#F8FAFD);border:1px solid #C0D4F5;border-right:4px solid var(--gold);border-radius:10px;padding:14px 18px;font-size:13px;color:#1A3A6B;font-family:'Tajawal',sans-serif;margin-bottom:12px;display:flex;align-items:center;gap:10px;}
    /* Print Buttons */
    .epro-print-btns{display:flex;gap:12px;margin-top:16px;justify-content:center;}
    /* Rating emojis */
    .rating-green{color:#27AE60;font-size:16px;}
    .rating-yellow{color:#D4AC0D;font-size:16px;}
    .rating-red{color:#E74C3C;font-size:16px;}
    /* EPRO footer on report */
    .epro-report-footer{background:linear-gradient(135deg,#0D1F3C,#1A3A6B);border-radius:12px;padding:20px;text-align:center;margin-top:12px;}
    .epro-report-footer .footer-brand{color:#C9A84C;font-size:18px;font-weight:900;letter-spacing:2px;margin-bottom:4px;}
    .epro-report-footer .footer-info{color:rgba(255,255,255,.65);font-size:12px;line-height:1.7;}
    @media(max-width:600px){.epro-row{grid-template-columns:1fr;}.epro-row.three{grid-template-columns:1fr 1fr;}.epro-scores-row{grid-template-columns:1fr;}.epro-card{padding:22px 16px;}}
    input[type="number"]{-moz-appearance:textfield;}
    input[type="number"]::-webkit-inner-spin-button,input[type="number"]::-webkit-outer-spin-button{-webkit-appearance:none;}
    </style>

    <div id="epro-app">

    <!-- HERO -->
    <div class="epro-hero">
        <div class="epro-logo-bar">
            <div>
                <div class="epro-logo-text">EPRO</div>
                <div class="epro-logo-sub">BUSINESS DEVELOPMENT</div>
            </div>
        </div>
        <div class="epro-badge">أداة التقييم الاستراتيجي</div>
        <h2>تقييم <span>جاهزية الطرح العام</span></h2>
        <p>قيّم شركتك بدقة وشفافية من خلال تحليل مالي وحوكمي شامل لمعرفة مدى جاهزيتها للطرح العام (IPO)</p>
    </div>
    <div class="epro-divider"></div>

    <div class="epro-intro" id="eproIntroBox">
        <p>تم تصميم هذا الاستبيان لمساعدة الشركات على تقييم جاهزيتها الحالية وتحديد فرص التطوير في مجالات رئيسية مثل الحوكمة، والهيكل المالي، وكفاءة العمليات، والتخطيط الاستراتيجي.</p>
        <div style="margin-top:22px;">
            <button class="epro-btn epro-bp" onclick="eproStart()" style="font-size:15px;padding:14px 36px;">ابدأ الاستبيان ←</button>
        </div>
    </div>

    <div class="epro-steps-wrap" id="eproStepsWrap" style="display:none;">
        <div class="epro-steps" id="eproSteps">
            <div class="epro-step active" data-s="0"><div class="epro-snum">١</div><div class="epro-slbl">بياناتك</div></div>
            <div class="epro-step" data-s="1"><div class="epro-snum">٢</div><div class="epro-slbl">الشركة</div></div>
            <div class="epro-step" data-s="2"><div class="epro-snum">٣</div><div class="epro-slbl">المالية</div></div>
            <div class="epro-step" data-s="3"><div class="epro-snum">٤</div><div class="epro-slbl">الحوكمة</div></div>
            <div class="epro-step" data-s="4"><div class="epro-snum">٥</div><div class="epro-slbl">IPO</div></div>
            <div class="epro-step" data-s="5"><div class="epro-snum">٦</div><div class="epro-slbl">النتائج</div></div>
        </div>
    </div>

    <!-- STEP 0: LEAD -->
    <div class="epro-card" id="eproS0">
        <div class="epro-ct">👤 بياناتك الشخصية <span class="etag">قبل البدء</span></div>
        <div class="epro-cs">ستصلك نتائج التقييم الكاملة على بريدك الإلكتروني فور الانتهاء من الاستبيان</div>
        <div class="epro-row">
            <div class="epro-fg"><label>الاسم الكامل <span class="req">*</span></label><input type="text" id="eLeadName" placeholder="اسمك الكامل"></div>
            <div class="epro-fg"><label>البريد الإلكتروني <span class="req">*</span></label><input type="email" id="eLeadEmail" placeholder="email@company.com" dir="ltr"></div>
        </div>
        <div class="epro-row one">
            <div class="epro-fg"><label>رقم الهاتف</label><input type="tel" id="eLeadPhone" placeholder="+966 5X XXX XXXX" dir="ltr"></div>
        </div>
        <div id="eproMsg0" class="epro-msg"></div>
        <div class="epro-btns"><button class="epro-btn epro-bp" onclick="eproGo(1)">ابدأ الاستبيان ←</button></div>
    </div>

    <!-- STEP 1: COMPANY -->
    <div class="epro-card" id="eproS1">
        <div class="epro-ct">🏢 البيانات الأساسية للشركة <span class="etag">١ من ٤</span></div>
        <div class="epro-cs">أدخل معلومات شركتك الأساسية للبدء في عملية التقييم</div>
        <div class="epro-row">
            <div class="epro-fg"><label>اسم الشركة <span class="req">*</span></label><input type="text" id="eCompanyName" placeholder="مثال: شركة الأفق للاستثمار"></div>
            <div class="epro-fg"><label>القطاع <span class="req">*</span></label><select id="eSector"><option value="">-- اختر القطاع --</option><option>المالي والمصرفي</option><option>التكنولوجيا والاتصالات</option><option>الصناعة والتصنيع</option><option>العقارات والبناء</option><option>الرعاية الصحية</option><option>الطاقة والبتروكيماويات</option><option>التجزئة والاستهلاك</option><option>الزراعة والغذاء</option><option>التعليم</option><option>أخرى</option></select></div>
        </div>
        <div class="epro-row">
            <div class="epro-fg"><label>الدولة <span class="req">*</span></label><select id="eCountry"><option value="">-- اختر الدولة --</option><option>المملكة العربية السعودية</option><option>الإمارات العربية المتحدة</option><option>مصر</option><option>الكويت</option><option>قطر</option><option>البحرين</option><option>عُمان</option><option>الأردن</option><option>المغرب</option><option>فلسطين</option><option>أخرى</option></select></div>
            <div class="epro-fg"><label>سنة التأسيس <span class="req">*</span></label><input type="number" id="eFoundedYear" placeholder="2010" min="1900" max="2025"></div>
        </div>
        <div class="epro-row">
            <div class="epro-fg"><label>الإيرادات السنوية <span class="req">*</span></label><input type="number" id="eAnnualRevenue" placeholder="0" step="0.01"></div>
            <div class="epro-fg"><label>عدد الموظفين <span class="req">*</span></label><input type="number" id="eEmployees" placeholder="0"></div>
        </div>
        <div class="epro-row one">
            <div class="epro-fg"><label>الهدف من التقييم <span class="req">*</span></label><select id="eGoal"><option value="">-- اختر الهدف --</option><option>طرح عام أولي (IPO)</option><option>تحسين الحوكمة</option><option>الحصول على تمويل</option><option>اندماج أو استحواذ</option></select></div>
        </div>
        <div class="epro-btns">
            <button class="epro-btn epro-bp" onclick="eproGo(2)">← التالي: البيانات المالية</button>
            <button class="epro-btn epro-bs" onclick="eproGo(0)">رجوع →</button>
        </div>
    </div>

    <!-- STEP 2: FINANCIAL -->
    <div class="epro-card" id="eproS2">
        <div class="epro-ct">📊 البيانات المالية <span class="etag">٢ من ٤</span></div>
        <div class="epro-cs">أدخل البيانات المالية بدقة – سيتم احتساب النسب تلقائياً</div>
        <div class="epro-sl">أولاً: قائمة الدخل</div>
        <div class="epro-row">
            <div class="epro-fg"><label>إجمالي الإيرادات – السنة الحالية <span class="req">*</span></label><input type="number" id="eRevCurrent" placeholder="0" step="0.01"></div>
            <div class="epro-fg"><label>إجمالي الإيرادات – السنة السابقة <span class="req">*</span></label><input type="number" id="eRevPrev" placeholder="0" step="0.01"></div>
        </div>
        <div class="epro-row">
            <div class="epro-fg"><label>صافي الربح (Net Profit) <span class="req">*</span></label><input type="number" id="eNetProfit" placeholder="0" step="0.01"></div>
            <div class="epro-fg"><label>إجمالي الربح (Gross Profit) <span class="req">*</span></label><input type="number" id="eGrossProfit" placeholder="0" step="0.01"></div>
        </div>
        <hr class="epro-hr">
        <div class="epro-sl">ثانياً: الميزانية العمومية</div>
        <div class="epro-row three">
            <div class="epro-fg"><label>إجمالي الأصول <span class="req">*</span></label><input type="number" id="eTotalAssets" placeholder="0" step="0.01"></div>
            <div class="epro-fg"><label>إجمالي الالتزامات <span class="req">*</span></label><input type="number" id="eTotalLiabilities" placeholder="0" step="0.01"></div>
            <div class="epro-fg"><label>حقوق الملكية (Equity) <span class="req">*</span></label><input type="number" id="eEquity" placeholder="0" step="0.01"></div>
        </div>
        <div class="epro-row three">
            <div class="epro-fg"><label>الأصول المتداولة <span class="req">*</span></label><input type="number" id="eCurrentAssets" placeholder="0" step="0.01"></div>
            <div class="epro-fg"><label>الالتزامات المتداولة <span class="req">*</span></label><input type="number" id="eCurrentLiabilities" placeholder="0" step="0.01"></div>
            <div class="epro-fg"><label>النقد وما في حكمه <span class="req">*</span></label><input type="number" id="eCash" placeholder="0" step="0.01"></div>
        </div>
        <div class="epro-row one">
            <div class="epro-fg"><label>إجمالي الديون (Total Debt) <span class="req">*</span></label><input type="number" id="eTotalDebt" placeholder="0" step="0.01"></div>
        </div>
        <div class="epro-btns">
            <button class="epro-btn epro-bp" onclick="eproGo(3)">← التالي: الحوكمة</button>
            <button class="epro-btn epro-bs" onclick="eproGo(1)">رجوع →</button>
        </div>
    </div>

    <!-- STEP 3: GOVERNANCE -->
    <div class="epro-card" id="eproS3">
        <div class="epro-ct">⚖️ تقييم الحوكمة <span class="etag">٣ من ٤</span></div>
        <div class="epro-cs">أجب على الأسئلة التالية بدقة للحصول على تقييم حوكمي شامل</div>
        <?php
        $gqs=[['g1','الفصل بين الملكية والإدارة التنفيذية'],['g2','وجود تدقيق داخلي (Internal Audit)'],['g3','وجود لجنة مراجعة (Audit Committee)'],['g5','سياسة إدارة المخاطر'],['g6','سياسة تضارب المصالح'],['g7','سياسة إفصاح (Disclosure Policy)'],['g8','سياسة علاقات المستثمرين'],['g9','توثيق محاضر اجتماعات مجلس الإدارة'],['g10','خطة التعاقب الإداري']];
        echo '<div style="display:flex;flex-direction:column;gap:14px;">';
        for($i=0;$i<count($gqs);$i+=2){echo '<div class="epro-row">';foreach(array_slice($gqs,$i,2) as $q){echo '<div class="epro-fg"><label>'.$q[1].'</label><div class="epro-tog"><input type="radio" name="'.$q[0].'" id="'.$q[0].'y" value="yes"><label for="'.$q[0].'y" class="ylbl">✅ نعم</label><input type="radio" name="'.$q[0].'" id="'.$q[0].'n" value="no"><label for="'.$q[0].'n" class="nlbl">❌ لا</label></div></div>';}echo '</div>';}
        echo '</div>';
        ?>
        <div class="epro-row" style="margin-top:14px;">
            <div class="epro-fg"><label>عدد أعضاء مجلس الإدارة المستقلين</label><input type="number" id="eIndMembers" placeholder="0" min="0"></div>
        </div>
        <div class="epro-btns">
            <button class="epro-btn epro-bp" onclick="eproGo(4)">← التالي: جاهزية IPO</button>
            <button class="epro-btn epro-bs" onclick="eproGo(2)">رجوع →</button>
        </div>
    </div>

    <!-- STEP 4: IPO -->
    <div class="epro-card" id="eproS4">
        <div class="epro-ct">🚀 جاهزية الطرح العام <span class="etag">٤ من ٤</span></div>
        <div class="epro-cs">أسئلة حول الجاهزية الهيكلية للطرح في السوق المالية</div>
        <?php
        $iqs=[['ipo1','هل لديكم قوائم مالية مدققة لسنتين أو أكثر؟'],['ipo2','هل تطبقون معايير IFRS للمحاسبة؟'],['ipo3','هل القوائم المالية موحدة (Consolidated)؟'],['ipo4','هل يوجد أعضاء مستقلون في مجلس الإدارة؟'],['ipo5','هل يوجد لجنة مراجعة (Audit Committee)؟'],['ipo6','هل يوجد سياسة إفصاح معتمدة؟'],['ipo7','هل يوجد سياسة علاقات مستثمرين؟'],['ipo8','هل التدفقات النقدية التشغيلية إيجابية؟']];
        echo '<div style="display:flex;flex-direction:column;gap:14px;">';
        for($i=0;$i<count($iqs);$i+=2){echo '<div class="epro-row">';foreach(array_slice($iqs,$i,2) as $q){echo '<div class="epro-fg"><label>'.$q[1].'</label><div class="epro-tog"><input type="radio" name="'.$q[0].'" id="'.$q[0].'y" value="yes"><label for="'.$q[0].'y" class="ylbl">✅ نعم</label><input type="radio" name="'.$q[0].'" id="'.$q[0].'n" value="no"><label for="'.$q[0].'n" class="nlbl">❌ لا</label></div></div>';}echo '</div>';}
        echo '</div>';
        ?>
        <div id="eproMsg4" class="epro-msg"></div>
        <div class="epro-btns">
            <button class="epro-btn epro-bp" id="eproSubBtn" onclick="eproSubmit()">🔍 إنهاء الاستبيان وعرض النتائج</button>
            <button class="epro-btn epro-bs" onclick="eproGo(3)">رجوع →</button>
        </div>
    </div>

    <!-- RESULTS -->
    <div class="epro-results" id="eproResults"></div>

    </div>

    <script>
    var eC=0,eA='<?=esc_js($ajax)?>',eN='<?=esc_js($nonce)?>';
    function eUS(s){document.querySelectorAll('.epro-step').forEach(function(e){var n=parseInt(e.dataset.s);e.classList.remove('active','done');if(n===s)e.classList.add('active');else if(n<s)e.classList.add('done');});}
    function eV(s){
        if(s===0){var n=document.getElementById('eLeadName').value.trim(),em=document.getElementById('eLeadEmail').value.trim();if(!n||!em){var m=document.getElementById('eproMsg0');m.className='epro-msg err';m.textContent='⚠️ يرجى إدخال الاسم والبريد الإلكتروني';return false;}}
        if(s===1){var ids=['eCompanyName','eSector','eCountry','eFoundedYear','eAnnualRevenue','eEmployees','eGoal'];for(var i=0;i<ids.length;i++){if(!document.getElementById(ids[i]).value.trim()){alert('⚠️ يرجى ملء جميع الحقول الإلزامية');document.getElementById(ids[i]).focus();return false;}}}
        if(s===2){var ids2=['eRevCurrent','eRevPrev','eNetProfit','eGrossProfit','eTotalAssets','eTotalLiabilities','eEquity','eCurrentAssets','eCurrentLiabilities','eCash','eTotalDebt'];for(var j=0;j<ids2.length;j++){if(document.getElementById(ids2[j]).value===''){alert('⚠️ يرجى ملء جميع الحقول المالية');document.getElementById(ids2[j]).focus();return false;}}}
        if(s===3){var gf=['g1','g2','g3','g5','g6','g7','g8','g9','g10'];for(var g=0;g<gf.length;g++){if(!document.querySelector('input[name="'+gf[g]+'"]:checked')){alert('⚠️ يرجى الإجابة على جميع أسئلة الحوكمة');return false;}}}
        if(s===4){var ip=['ipo1','ipo2','ipo3','ipo4','ipo5','ipo6','ipo7','ipo8'];for(var p=0;p<ip.length;p++){if(!document.querySelector('input[name="'+ip[p]+'"]:checked')){alert('⚠️ يرجى الإجابة على جميع أسئلة جاهزية الطرح');return false;}}}
        return true;
    }
    function eproStart(){
        document.getElementById('eproIntroBox').style.display='none';
        document.getElementById('eproStepsWrap').style.display='block';
        document.getElementById('eproS0').classList.add('active');
        window.scrollTo({top:0,behavior:'smooth'});
    }
    function eproGo(n){
        if(n>eC&&!eV(eC))return;
        var cs=['eproS0','eproS1','eproS2','eproS3','eproS4'];
        if(cs[eC])document.getElementById(cs[eC]).classList.remove('active');
        if(cs[n])document.getElementById(cs[n]).classList.add('active');
        eC=n;eUS(n);window.scrollTo({top:0,behavior:'smooth'});
    }
    function eGR(name){var el=document.querySelector('input[name="'+name+'"]:checked');return el?el.value:'no';}
    function eproSubmit(){
        var btn=document.getElementById('eproSubBtn');
        btn.disabled=true;btn.textContent='⏳ جاري المعالجة...';
        var fd=new FormData();fd.append('action','epro_submit');fd.append('nonce',eN);
        var d={leadName:document.getElementById('eLeadName').value,leadEmail:document.getElementById('eLeadEmail').value,leadPhone:document.getElementById('eLeadPhone').value,companyName:document.getElementById('eCompanyName').value,sector:document.getElementById('eSector').value,country:document.getElementById('eCountry').value,foundedYear:document.getElementById('eFoundedYear').value,annualRevenue:document.getElementById('eAnnualRevenue').value,employees:document.getElementById('eEmployees').value,assessmentGoal:document.getElementById('eGoal').value,revCurrent:document.getElementById('eRevCurrent').value,revPrev:document.getElementById('eRevPrev').value,netProfit:document.getElementById('eNetProfit').value,grossProfit:document.getElementById('eGrossProfit').value,totalAssets:document.getElementById('eTotalAssets').value,totalLiabilities:document.getElementById('eTotalLiabilities').value,equity:document.getElementById('eEquity').value,currentAssets:document.getElementById('eCurrentAssets').value,currentLiabilities:document.getElementById('eCurrentLiabilities').value,cash:document.getElementById('eCash').value,totalDebt:document.getElementById('eTotalDebt').value};
        var gov={g1:eGR('g1'),g2:eGR('g2'),g3:eGR('g3'),g5:eGR('g5'),g6:eGR('g6'),g7:eGR('g7'),g8:eGR('g8'),g9:eGR('g9'),g10:eGR('g10'),independentMembers:document.getElementById('eIndMembers').value||0};
        var ipo={ipo1:eGR('ipo1'),ipo2:eGR('ipo2'),ipo3:eGR('ipo3'),ipo4:eGR('ipo4'),ipo5:eGR('ipo5'),ipo6:eGR('ipo6'),ipo7:eGR('ipo7'),ipo8:eGR('ipo8')};
        for(var k in d)fd.append('data['+k+']',d[k]);
        for(var gk in gov)fd.append('data[governance]['+gk+']',gov[gk]);
        for(var ik in ipo)fd.append('data[ipo]['+ik+']',ipo[ik]);
        fetch(eA,{method:'POST',body:fd}).then(function(r){return r.json();}).then(function(res){
            if(res.success){eproSR(res.data,d.companyName,d.leadEmail);}
            else{var m=document.getElementById('eproMsg4');m.className='epro-msg err';m.textContent='حدث خطأ، يرجى المحاولة مجدداً';btn.disabled=false;btn.textContent='🔍 إنهاء الاستبيان وعرض النتائج';}
        }).catch(function(){var m=document.getElementById('eproMsg4');m.className='epro-msg err';m.textContent='حدث خطأ في الاتصال';btn.disabled=false;btn.textContent='🔍 إنهاء الاستبيان وعرض النتائج';});
    }
    function pct(v){return isFinite(v)&&v!=-999?v.toFixed(1)+'%':'—';}
    function rto(v){return isFinite(v)&&v!=999?v.toFixed(2)+'x':'—';}
    function gCL(s){return s>=80?'#27AE60':s>=65?'#D4AC0D':s>=50?'#E67E22':'#E74C3C';}
    function gRating(emoji){
        if(emoji==='🟢')return '<span style="color:#27AE60;font-size:14px;">●</span> <span style="color:#27AE60;font-size:12px;font-weight:700;">ممتاز</span>';
        if(emoji==='🟡')return '<span style="color:#D4AC0D;font-size:14px;">●</span> <span style="color:#D4AC0D;font-size:12px;font-weight:700;">مقبول</span>';
        return '<span style="color:#E74C3C;font-size:14px;">●</span> <span style="color:#E74C3C;font-size:12px;font-weight:700;">ضعيف</span>';
    }
    function eproSR(data, companyName, email) {
        document.getElementById('eproS4').classList.remove('active');
        eUS(5);
        var d=data, r=d.ratios, c=d.classifications, ib=d.isBlocked;
        var ov=d.overall, fin=d.finScore, gs=d.govScore, is=d.ipoScore;
        var fColor=gCL(fin), gColor=gCL(gs), iColor=ib?'#C0392B':gCL(is);
        var statusText=ib?'معطّل – الطرح غير متاح':(ov>=80?'ممتاز – جاهز للطرح':ov>=65?'جيد – يحتاج تحسين بسيط':ov>=50?'متوسط – يحتاج إعادة هيكلة':'ضعيف – مخاطر عالية');
        var statusBg=ib?'rgba(192,57,43,.2)':(ov>=80?'rgba(39,174,96,.2)':ov>=65?'rgba(212,172,13,.2)':ov>=50?'rgba(230,126,34,.2)':'rgba(231,76,60,.2)');
        var statusColor=ib?'#FFAAAA':(ov>=80?'#7FD4A8':ov>=65?'#F8E07A':ov>=50?'#F5B77A':'#FFAAAA');

        var h = `
        <div class="epro-rh">
            <div class="epro-rh-top">
                <span class="epro-rh-logo">EPRO</span>
                <span class="epro-rh-url">eprome.com</span>
            </div>
            <div class="epro-rh-body">
                <div class="cn">تقرير تقييم جاهزية الطرح العام</div>
                <div class="company-title">${companyName}</div>
                <div class="epro-score-ring">
                    <div class="sn">${ov}</div>
                    <div class="sl">من 100</div>
                </div>
                <div class="epro-status-pill" style="background:${statusBg};color:${statusColor};">${statusText}</div>
            </div>
        </div>

        <div class="epro-email-notice">
            <span style="font-size:20px;">📧</span>
            <span>تم إرسال نسخة كاملة من التقرير إلى: <strong>${email}</strong></span>
        </div>

        <div class="epro-scores-row">
            <div class="epro-sc-card">
                <div class="sc-label">الصحة المالية</div>
                <div class="sc-val" style="color:${fColor}">${fin}</div>
                <div class="sc-bar"><div class="sc-bar-fill" style="width:${fin}%;background:${fColor}"></div></div>
            </div>
            <div class="epro-sc-card">
                <div class="sc-label">الحوكمة</div>
                <div class="sc-val" style="color:${gColor}">${gs}</div>
                <div class="sc-bar"><div class="sc-bar-fill" style="width:${gs}%;background:${gColor}"></div></div>
            </div>
            <div class="epro-sc-card">
                <div class="sc-label">جاهزية الطرح</div>
                <div class="sc-val" style="color:${iColor};font-size:${ib?'13px':'28px'}">${ib?'<span style="display:inline-block;background:#C0392B;color:#fff;padding:3px 10px;border-radius:6px;font-size:12px;font-weight:800;">BLOCKED</span>':is}</div>
                <div class="sc-bar"><div class="sc-bar-fill" style="width:${ib?0:is}%;background:${iColor}"></div></div>
            </div>
        </div>`;

        if(ib && d.blockers && d.blockers.length>0){
            h += `<div class="epro-blockers"><h4>معوقات الطرح العام</h4><ul>`;
            d.blockers.forEach(b => h += `<li>${b}</li>`);
            h += `</ul></div>`;
        }

        h += `
        <div class="epro-section">
            <div class="epro-section-head"><span class="sec-ico">📈</span><span class="sec-title">تفاصيل النسب المالية</span></div>
            <div class="epro-section-body">
            <table class="epro-rt">
                <thead><tr>
                    <th>المؤشر</th><th style="text-align:center;">القيمة</th><th style="text-align:center;">التصنيف</th>
                </tr></thead><tbody>`;

        var rows=[
            ['هامش صافي الربح',pct(r.netMargin),c.netMargin.emoji],
            ['العائد على حقوق الملكية (ROE)',pct(r.roe),c.roe.emoji],
            ['العائد على الأصول (ROA)',pct(r.roa),c.roa.emoji],
            ['هامش الربح الإجمالي',pct(r.grossMargin),c.grossMargin.emoji],
            ['معدل نمو الإيرادات',pct(r.revGrowth),c.revGrowth.emoji],
            ['نسبة السيولة الجارية',rto(r.currentRatio),c.currentRatio.emoji],
            ['نسبة النقد',rto(r.cashRatio),c.cashRatio.emoji],
            ['الدين إلى حقوق الملكية',rto(r.debtToEquity),c.debtToEquity.emoji],
            ['الدين إلى الأصول',pct(r.debtToAsset),c.debtToAsset.emoji],
            ['حقوق الملكية إلى الأصول',pct(r.equityToAsset),c.equityToAsset.emoji]
        ];
        rows.forEach(rw => {
            h += `<tr><td>${rw[0]}</td><td style="text-align:center;font-weight:700;color:#1A3A6B;">${rw[1]}</td><td style="text-align:center;">${gRating(rw[2])}</td></tr>`;
        });

        h += `</tbody></table></div></div>

        <div class="epro-report-footer">
            <div class="footer-brand">EPRO</div>
            <div class="footer-info">
                info@eprome.com &nbsp;|&nbsp; www.eprome.com<br>
                Building No. 3630, 2nd Floor, Al Urubah Street, Al Wurud District, Riyadh 12252, KSA<br>
                +966 56 630 0876
            </div>
        </div>

        <div class="epro-print-btns">
            <button class="epro-btn epro-bs" onclick="eproPrint()">🖨️ طباعة التقرير PDF</button>
            <button class="epro-btn epro-bp" onclick="location.reload()">تقييم شركة جديدة</button>
        </div>`;

        var el=document.getElementById('eproResults');
        el.innerHTML=h; el.classList.add('active');
        window.scrollTo({top:0,behavior:'smooth'});
    }

    /* ============================================================
       PRINT FUNCTION — Professional A4 with small, clean ratings
    ============================================================ */
    function eproPrint() {
        var el = document.getElementById('eproResults');
        var clone = el.cloneNode(true);
        clone.querySelectorAll('.epro-print-btns,.epro-email-notice').forEach(function(n){ n.remove(); });

        // Fix sc-val: rewrite innerHTML directly to remove oversized inline font-size
        clone.querySelectorAll('.epro-sc-card').forEach(function(card){
            var val = card.querySelector('.sc-val');
            if(!val) return;
            var rawText = (val.innerText || val.textContent || '').trim();
            var color   = val.style.color || '#1A3A6B';
            var isBlocked = rawText.indexOf('BLOCKED') !== -1 || rawText === '⛔';
            val.removeAttribute('style');
            val.style.marginBottom = '4px';
            if(isBlocked){
                val.innerHTML = '<span style="display:inline-block;background:#C0392B;color:#fff;padding:2px 8px;border-radius:4px;font-size:9px;font-weight:800;">محظور</span>';
            } else {
                val.innerHTML = '<span style="font-size:20px;font-weight:900;color:'+color+';">' + rawText + '</span>';
            }
        });

        // Fix rating dots -> text badges
        clone.querySelectorAll('td').forEach(function(td){
            td.innerHTML = td.innerHTML
                .replace(/<span[^>]*color:#27AE60[^>]*>●<\/span>\s*<span[^>]*>ممتاز<\/span>/g,'<span style="padding:2px 6px;border-radius:3px;background:#E8F8F0;color:#27AE60;font-size:8px;font-weight:700;">✓ ممتاز</span>')
                .replace(/<span[^>]*color:#D4AC0D[^>]*>●<\/span>\s*<span[^>]*>مقبول<\/span>/g,'<span style="padding:2px 6px;border-radius:3px;background:#FEF9E7;color:#D4AC0D;font-size:8px;font-weight:700;">~ مقبول</span>')
                .replace(/<span[^>]*color:#E74C3C[^>]*>●<\/span>\s*<span[^>]*>ضعيف<\/span>/g,'<span style="padding:2px 6px;border-radius:3px;background:#FEF0EE;color:#E74C3C;font-size:8px;font-weight:700;">✕ ضعيف</span>');
        });

        var css = `
        @import url('https://fonts.googleapis.com/css2?family=Cairo:wght@400;700;900&display=swap');
        @page { margin: 12mm 14mm; size: A4 portrait; }
        /* Kill oversized emoji images in print */
        img { max-width: 0 !important; max-height: 0 !important; display: none !important; }
        .epro-score-ring .sn, .sc-val span { font-size: inherit !important; }
        * { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; box-sizing: border-box; }
        body { font-family: 'Cairo', Arial, sans-serif; direction: rtl; font-size: 11px; line-height: 1.5; color: #1A2B4A; background: #fff; margin: 0; padding: 0; }
        /* ── Header ── */
        .epro-rh { background: #1A3A6B !important; border-radius: 8px; overflow: hidden; margin-bottom: 12px; }
        .epro-rh-top { background: rgba(201,168,76,.25) !important; padding: 6px 14px; display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid rgba(201,168,76,.3); }
        .epro-rh-logo { color: #C9A84C; font-size: 13px; font-weight: 900; letter-spacing: 2px; }
        .epro-rh-url { color: rgba(255,255,255,.5); font-size: 9px; }
        .epro-rh-body { padding: 14px 16px; text-align: center; }
        .epro-rh .cn { font-size: 9px; color: rgba(255,255,255,.65); letter-spacing: 1px; margin-bottom: 4px; }
        .epro-rh .company-title { font-size: 17px; font-weight: 900; color: #fff; margin-bottom: 10px; }
        .epro-score-ring { width: 64px; height: 64px; border-radius: 50%; border: 2px solid rgba(201,168,76,.5); display: flex; flex-direction: column; align-items: center; justify-content: center; margin: 0 auto 10px; background: rgba(255,255,255,.1) !important; }
        .epro-score-ring .sn { font-size: 22px; font-weight: 900; color: #fff; line-height: 1; }
        .epro-score-ring .sl { font-size: 8px; color: rgba(255,255,255,.6); margin-top: 2px; }
        .epro-status-pill { display: inline-block; padding: 4px 14px; border-radius: 20px; font-size: 10px; font-weight: 800; border: 1px solid rgba(255,255,255,.35); }
        /* ── 3-Score Cards ── */
        .epro-scores-row { display: grid !important; grid-template-columns: repeat(3,1fr) !important; gap: 8px; margin-bottom: 12px; }
        .epro-sc-card { background: #F8FAFD !important; border: 1px solid #D0DCF0; border-radius: 6px; padding: 10px 8px; text-align: center; }
        .epro-sc-card .sc-label { font-size: 9px; color: #6B7FA3; margin-bottom: 4px; }
        .epro-sc-card .sc-val { font-size: 16px !important; font-weight: 900; line-height: 1.2; margin-bottom: 4px; }
        .epro-sc-card .sc-val * { font-size: 10px !important; }
        .epro-sc-card .sc-bar { height: 4px; background: #EEF4FF; border-radius: 4px; overflow: hidden; margin-top: 4px; }
        .epro-sc-card .sc-bar-fill { height: 100%; border-radius: 4px; }
        /* ── Kill oversized ── */
        [style*="font-size:48px"],[style*="font-size: 48px"],[style*="font-size:36px"],[style*="font-size: 36px"],[style*="font-size:28px"],[style*="font-size: 28px"] { font-size: 16px !important; }
        /* ── Blockers ── */
        .epro-blockers { background: #FEF0EE !important; border: 1px solid #FADBD8; border-right: 3px solid #C0392B; border-radius: 6px; padding: 10px 12px; margin-bottom: 12px; }
        .epro-blockers h4 { color: #C0392B; font-size: 11px; margin: 0 0 6px; font-weight: 800; }
        .epro-blockers ul { list-style: none; margin: 0; padding: 0; }
        .epro-blockers ul li { padding: 3px 0; font-size: 10px; color: #A93226; display: flex; align-items: center; gap: 6px; border-bottom: 1px solid rgba(192,57,43,.1); }
        .epro-blockers ul li:last-child { border-bottom: none; }
        .epro-blockers ul li::before { content: 'x'; width: 12px; height: 12px; background: #C0392B; color: #fff; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-size: 7px; flex-shrink: 0; }
        /* ── Sections ── */
        .epro-section { background: #fff; border: 1px solid #D0DCF0; border-radius: 6px; margin-bottom: 12px; overflow: hidden; }
        .epro-section-head { background: #1A3A6B !important; padding: 7px 12px; display: flex; align-items: center; gap: 6px; }
        .epro-section-head .sec-ico { font-size: 11px; }
        .epro-section-head .sec-title { color: #C9A84C; font-size: 10px; font-weight: 800; letter-spacing: .5px; }
        /* ── Table ── */
        .epro-rt { width: 100%; border-collapse: collapse; }
        .epro-rt th { background: #EEF4FF !important; padding: 7px 10px; text-align: right; font-size: 9.5px; color: #1A3A6B; font-weight: 700; border-bottom: 1.5px solid #D0DCF0; }
        .epro-rt td { padding: 6px 10px; font-size: 10px; color: #1A2B4A; border-bottom: 1px solid #F0F4FA; }
        .epro-rt tr:last-child td { border-bottom: none; }
        .epro-rt tr:nth-child(even) td { background: #FAFBFD !important; }
        .epro-rt td span { font-size: 9px !important; padding: 2px 6px !important; }
        /* ── Footer ── */
        .epro-report-footer { background: #1A3A6B !important; border-radius: 6px; padding: 10px 14px; text-align: center; margin-top: 12px; }
        .epro-report-footer .footer-brand { color: #C9A84C; font-size: 12px; font-weight: 900; letter-spacing: 2px; margin-bottom: 3px; }
        .epro-report-footer .footer-info { color: rgba(255,255,255,.7); font-size: 9px; line-height: 1.6; }
        .epro-email-notice { display: none !important; }
        `;

        var fullHTML = `<!DOCTYPE html>
        <html dir="rtl">
        <head>
            <meta charset="UTF-8">
            <title>تقرير تقييم جاهزية الطرح العام | EPRO</title>
            <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;700;900&display=swap" rel="stylesheet">
            <style>${css}</style>
        </head>
        <body>${clone.innerHTML}</body>
        </html>`;

        var blob = new Blob([fullHTML], {type:'text/html'});
        var url = URL.createObjectURL(blob);
        var w = window.open(url, '_blank');
        if(w) w.onload = () => setTimeout(() => w.print(), 800);
    }
    </script>
    <?php
    return ob_get_clean();
}

// ==============================
// ENGLISH VERSION
// ==============================
add_shortcode('epro_assessment_en', 'epro_render_form_en');
add_action('wp_ajax_epro_submit_en', 'epro_handle_submit_en');
add_action('wp_ajax_nopriv_epro_submit_en', 'epro_handle_submit_en');

function epro_handle_submit_en() {
    check_ajax_referer('epro_nonce_en', 'nonce');
    global $wpdb;
    $table = $wpdb->prefix . 'epro_assessments';
    $data = $_POST['data'] ?? [];

    $rev=floatval($data['revCurrent']??0); $revPrev=floatval($data['revPrev']??0);
    $net=floatval($data['netProfit']??0); $gross=floatval($data['grossProfit']??0);
    $assets=floatval($data['totalAssets']??0); $eq=floatval($data['equity']??0);
    $curA=floatval($data['currentAssets']??0); $curL=floatval($data['currentLiabilities']??0);
    $cash=floatval($data['cash']??0); $debt=floatval($data['totalDebt']??0);

    $ratios=['netMargin'=>$rev>0?($net/$rev)*100:0,'roe'=>$eq>0?($net/$eq)*100:-999,'roa'=>$assets>0?($net/$assets)*100:0,'grossMargin'=>$rev>0?($gross/$rev)*100:0,'revGrowth'=>$revPrev>0?(($rev-$revPrev)/$revPrev)*100:0,'currentRatio'=>$curL>0?$curA/$curL:0,'cashRatio'=>$curL>0?$cash/$curL:0,'debtToEquity'=>$eq>0?$debt/$eq:999,'debtToAsset'=>$assets>0?($debt/$assets)*100:0,'equityToAsset'=>$assets>0?($eq/$assets)*100:0];

    if(!function_exists('ec_en')){function ec_en($k,$v,$eqv){
        if($k==='roe'&&$eqv<=0)return['score'=>20,'emoji'=>'🔴'];
        $m=['netMargin'=>[15,5],'roe'=>[20,10],'roa'=>[10,5],'grossMargin'=>[40,25],'revGrowth'=>[10,0]];
        if(isset($m[$k])){if($v>$m[$k][0])return['score'=>100,'emoji'=>'🟢'];if($v>=$m[$k][1])return['score'=>60,'emoji'=>'🟡'];return['score'=>20,'emoji'=>'🔴'];}
        if($k==='currentRatio'){if($v>=1.5&&$v<=3)return['score'=>100,'emoji'=>'🟢'];if($v>=1)return['score'=>60,'emoji'=>'🟡'];return['score'=>20,'emoji'=>'🔴'];}
        if($k==='cashRatio'){if($v>0.5)return['score'=>100,'emoji'=>'🟢'];if($v>=0.2)return['score'=>60,'emoji'=>'🟡'];return['score'=>20,'emoji'=>'🔴'];}
        if($k==='debtToEquity'){if($v<1)return['score'=>100,'emoji'=>'🟢'];if($v<=2)return['score'=>60,'emoji'=>'🟡'];return['score'=>20,'emoji'=>'🔴'];}
        if($k==='debtToAsset'){if($v<40)return['score'=>100,'emoji'=>'🟢'];if($v<=60)return['score'=>60,'emoji'=>'🟡'];return['score'=>20,'emoji'=>'🔴'];}
        if($k==='equityToAsset'){if($v>40)return['score'=>100,'emoji'=>'🟢'];if($v>=20)return['score'=>60,'emoji'=>'🟡'];return['score'=>20,'emoji'=>'🔴'];}
        return['score'=>20,'emoji'=>'🔴'];
    }}
    $cls=[];foreach($ratios as $k=>$v)$cls[$k]=ec_en($k,$v,$eq);
    $w=['netMargin'=>0.10,'roe'=>0.10,'roa'=>0.08,'grossMargin'=>0.07,'revGrowth'=>0.05,'currentRatio'=>0.15,'cashRatio'=>0.10,'debtToEquity'=>0.25,'debtToAsset'=>0.05,'equityToAsset'=>0.05];
    $fin=0;foreach($w as $k=>$wt)$fin+=$cls[$k]['score']*$wt;
    $co=($eq<=0||$ratios['debtToEquity']>2.5||$ratios['currentRatio']<0.8||$ratios['revGrowth']<0);
    if($co)$fin=min($fin,45);

    $gi=['g1'=>0.10,'g2'=>0.10,'g3'=>0.15,'g5'=>0.10,'g6'=>0.10,'g7'=>0.10,'g8'=>0.10,'g9'=>0.05,'g10'=>0.05];
    $gov=$data['governance']??[]; $gs=0;
    foreach($gi as $k=>$wt)$gs+=(($gov[$k]??'no')==='yes'?100:20)*$wt;
    $im=intval($gov['independentMembers']??0);$gs+=($im>=3?100:($im>=1?60:20))*0.15;

    $ipo=$data['ipo']??[];$bl=[];
    if(($ipo['ipo1']??'no')==='no')$bl[]='Less than 2 years of audited financial statements';
    if(($ipo['ipo2']??'no')==='no')$bl[]='IFRS standards not applied';
    if(($ipo['ipo3']??'no')==='no')$bl[]='Financial statements not consolidated';
    if(($ipo['ipo4']??'no')==='no')$bl[]='No independent board members';
    if(($ipo['ipo5']??'no')==='no')$bl[]='No Audit Committee';
    if(($ipo['ipo6']??'no')==='no')$bl[]='No Disclosure Policy';
    if(($ipo['ipo7']??'no')==='no')$bl[]='No Investor Relations Policy';
    if($eq<=0)$bl[]='Negative shareholders equity';
    if($ratios['debtToEquity']>2)$bl[]='Debt-to-Equity ratio exceeds 2x';
    if(($ipo['ipo8']??'no')==='no')$bl[]='Negative operating cash flows';

    $isB=count($bl)>0;$is=$isB?0:((10-count($bl))/10)*100;
    $ov=($fin*0.40)+($gs*0.40)+($is*0.20);
    $oc=$ov>=80?'Excellent – IPO Ready':($ov>=65?'Good – Minor Improvements Needed':($ov>=50?'Average – Restructuring Required':'Weak – High Risk'));

    $wpdb->insert($table,['lead_name'=>sanitize_text_field($data['leadName']??''),'lead_email'=>sanitize_email($data['leadEmail']??''),'lead_phone'=>sanitize_text_field($data['leadPhone']??''),'company_name'=>sanitize_text_field($data['companyName']??''),'sector'=>sanitize_text_field($data['sector']??''),'country'=>sanitize_text_field($data['country']??''),'founded_year'=>intval($data['foundedYear']??0),'annual_revenue'=>floatval($data['annualRevenue']??0),'employees'=>intval($data['employees']??0),'assessment_goal'=>sanitize_text_field($data['assessmentGoal']??''),'rev_current'=>$rev,'rev_prev'=>$revPrev,'net_profit'=>$net,'gross_profit'=>$gross,'total_assets'=>$assets,'total_liabilities'=>floatval($data['totalLiabilities']??0),'equity'=>$eq,'current_assets'=>$curA,'current_liabilities'=>$curL,'cash'=>$cash,'total_debt'=>$debt,'governance_data'=>json_encode($gov),'ipo_data'=>json_encode($ipo),'results_json'=>json_encode(['ratios'=>$ratios,'classifications'=>$cls,'blockers'=>$bl,'criticalOverride'=>$co]),'financial_score'=>round($fin,2),'governance_score'=>round($gs,2),'ipo_score'=>round($is,2),'overall_score'=>round($ov,2),'ipo_blocked'=>$isB?1:0,'overall_class'=>$oc]);

    $ce=sanitize_email($data['leadEmail']??'');
    $cn=sanitize_text_field($data['companyName']??'');
    $ln=sanitize_text_field($data['leadName']??'');
    $emailData=['name'=>$ln,'company'=>$cn,'email'=>$ce,'phone'=>sanitize_text_field($data['leadPhone']??''),'sector'=>sanitize_text_field($data['sector']??''),'country'=>sanitize_text_field($data['country']??''),'foundedYear'=>intval($data['foundedYear']??0),'employees'=>intval($data['employees']??0),'annualRevenue'=>floatval($data['annualRevenue']??0),'assessmentGoal'=>sanitize_text_field($data['assessmentGoal']??'')];
    $emailScores=['fin'=>round($fin,1),'gov'=>round($gs,1),'ipo'=>round($is,1),'overall'=>round($ov,1),'class'=>$oc,'blocked'=>$isB,'blockers'=>$bl,'ratios'=>$ratios,'revCurrent'=>$rev,'revPrev'=>$revPrev,'net'=>$net,'gross'=>$gross,'assets'=>$assets,'equity'=>$eq,'curA'=>$curA,'curL'=>$curL,'debt'=>$debt,'cash'=>$cash,'govData'=>$gov,'ipoData'=>$ipo];
    $htmlEmail=epro_build_email_html('en',$emailData,$emailScores);
    $hdrs=['Content-Type: text/html; charset=UTF-8','From: EPRO <info@eprome.com>'];
    $htmlEmailAdmin=epro_build_email_html('en',$emailData,$emailScores,true);
    wp_mail('info@eprome.com',"New Assessment – {$cn} | EPRO",$htmlEmailAdmin,$hdrs);
    if($ce)wp_mail($ce,"IPO Readiness Assessment Results – {$cn} | EPRO",$htmlEmail,$hdrs);

    wp_send_json_success(['finScore'=>round($fin,1),'govScore'=>round($gs,1),'ipoScore'=>round($is,1),'overall'=>round($ov,1),'overallClass'=>$oc,'isBlocked'=>$isB,'blockers'=>$bl,'ratios'=>$ratios,'classifications'=>$cls,'criticalOverride'=>$co]);
}

function epro_render_form_en(){
    $nonce=wp_create_nonce('epro_nonce_en');
    $ajax=admin_url('admin-ajax.php');
    ob_start();
    ?>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700;900&display=swap" rel="stylesheet">
    <style>
    /* ===================== EPRO ENGLISH FORM STYLES ===================== */
    #epro-app-en{--blue:#1A3A6B;--gold:#C9A84C;--blue-mid:#1E4D8C;--blue-l:#2D6BC4;--green:#27AE60;--gray:#F4F7FB;--border:#D0DCF0;--text:#1A2B4A;--muted:#6B7FA3;font-family:'Inter',sans-serif;direction:ltr;color:var(--text);margin:48px auto 80px;max-width:860px;padding:0 20px;}
    #epro-app-en *{box-sizing:border-box;}
    #epro-app-en .epro-hero{background:linear-gradient(135deg,#0D1F3C 0%,#1A3A6B 60%,#2D6BC4 100%);padding:48px 24px 36px;text-align:center;border-radius:16px;margin-bottom:6px;position:relative;overflow:hidden;}
    #epro-app-en .epro-hero::before{content:'';position:absolute;top:-40%;left:50%;transform:translateX(-50%);width:600px;height:600px;background:radial-gradient(circle,rgba(201,168,76,.08),transparent 70%);pointer-events:none;}
    #epro-app-en .epro-logo-bar{display:flex;align-items:center;justify-content:center;gap:10px;margin-bottom:20px;}
    #epro-app-en .epro-logo-text{color:#C9A84C;font-size:22px;font-weight:900;letter-spacing:3px;}
    #epro-app-en .epro-logo-sub{color:rgba(255,255,255,.5);font-size:10px;letter-spacing:1px;margin-top:2px;}
    #epro-app-en .epro-badge{display:inline-block;background:rgba(201,168,76,.15);border:1px solid rgba(201,168,76,.4);color:#C9A84C;padding:5px 18px;border-radius:20px;font-size:12px;letter-spacing:1px;margin-bottom:14px;}
    #epro-app-en .epro-hero h2{font-size:clamp(22px,4vw,36px);font-weight:900;color:#fff;margin:0 0 10px;}
    #epro-app-en .epro-hero h2 span{color:#C9A84C;}
    #epro-app-en .epro-hero p{font-size:14px;color:rgba(255,255,255,.75);max-width:620px;margin:0 auto;line-height:1.8;}
    #epro-app-en .epro-divider{height:3px;background:linear-gradient(90deg,transparent,#C9A84C,transparent);margin:0;}
    #epro-app-en .epro-intro{background:#EEF4FF;border:1px solid #C0D4F5;border-top:4px solid var(--gold);border-radius:14px;padding:26px 30px;margin-bottom:6px;}
    #epro-app-en .epro-intro p{font-size:14px;color:#2A3F6B;line-height:1.9;margin:0;}
    #epro-app-en .epro-steps-wrap{background:#fff;border:1px solid var(--border);border-radius:14px;padding:20px 24px;margin-bottom:6px;}
    #epro-app-en .epro-steps{display:flex;justify-content:center;gap:0;max-width:640px;margin:0 auto;}
    #epro-app-en .epro-step{flex:1;display:flex;flex-direction:column;align-items:center;position:relative;}
    #epro-app-en .epro-step:not(:last-child)::after{content:'';position:absolute;top:15px;right:0;width:100%;height:2px;background:#D0DCF0;z-index:0;}
    #epro-app-en .epro-step.done:not(:last-child)::after{background:var(--gold);}
    #epro-app-en .epro-snum{width:30px;height:30px;border-radius:50%;border:2px solid #D0DCF0;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:700;color:var(--muted);background:#fff;position:relative;z-index:1;transition:all .3s;}
    #epro-app-en .epro-step.active .epro-snum{border-color:var(--gold);background:var(--gold);color:#fff;}
    #epro-app-en .epro-step.done .epro-snum{border-color:var(--gold);background:var(--blue);color:#C9A84C;}
    #epro-app-en .epro-slbl{font-size:10px;color:var(--muted);margin-top:5px;text-align:center;}
    #epro-app-en .epro-step.active .epro-slbl{color:var(--gold);font-weight:700;}
    #epro-app-en .epro-step.done .epro-slbl{color:var(--blue);}
    #epro-app-en .epro-card{background:#fff;border:1px solid var(--border);border-radius:14px;padding:36px;margin-bottom:6px;display:none;animation:eproFen .35s ease;box-shadow:0 2px 16px rgba(26,58,107,.06);}
    #epro-app-en .epro-card.active{display:block;}
    @keyframes eproFen{from{opacity:0;transform:translateY(14px)}to{opacity:1;transform:translateY(0)}}
    #epro-app-en .epro-ct{font-size:18px;font-weight:800;color:var(--blue);margin-bottom:4px;display:flex;align-items:center;gap:9px;}
    #epro-app-en .etag{display:inline-block;background:#EEF4FF;color:var(--blue-l);padding:2px 10px;border-radius:6px;font-size:11px;font-weight:600;}
    #epro-app-en .epro-cs{font-size:13px;color:var(--muted);margin-bottom:26px;line-height:1.7;}
    #epro-app-en .epro-sl{font-size:12px;font-weight:700;color:var(--blue);background:linear-gradient(90deg,#EEF4FF,transparent);padding:6px 14px;border-left:3px solid var(--gold);border-radius:4px;display:inline-block;margin-bottom:16px;}
    #epro-app-en hr.epro-hr{border:none;border-top:1px solid var(--border);margin:22px 0;}
    #epro-app-en .epro-row{display:grid;grid-template-columns:1fr 1fr;gap:18px;margin-bottom:18px;}
    #epro-app-en .epro-row.one{grid-template-columns:1fr;}
    #epro-app-en .epro-row.three{grid-template-columns:1fr 1fr 1fr;}
    #epro-app-en .epro-fg{display:flex;flex-direction:column;gap:6px;}
    #epro-app-en .epro-fg label{font-size:13px;font-weight:700;color:var(--text);}
    #epro-app-en .epro-fg label .req{color:#E74C3C;}
    #epro-app-en .epro-fg input,#epro-app-en .epro-fg select{background:#F8FAFD;border:1.5px solid var(--border);border-radius:9px;padding:12px 14px;color:var(--text);font-family:'Inter',sans-serif;font-size:13px;outline:none;width:100%;direction:ltr;transition:all .2s;}
    #epro-app-en .epro-fg input:focus,#epro-app-en .epro-fg select:focus{border-color:var(--gold);background:#fff;box-shadow:0 0 0 3px rgba(201,168,76,.12);}
    #epro-app-en .epro-tog{display:flex;background:#F8FAFD;border:1.5px solid var(--border);border-radius:9px;overflow:hidden;}
    #epro-app-en .epro-tog input[type="radio"]{display:none;}
    #epro-app-en .epro-tog label{flex:1;text-align:center;padding:10px;cursor:pointer;font-size:13px;font-weight:600;color:var(--muted);transition:all .2s;}
    #epro-app-en .epro-tog input[type="radio"]:checked + .ylbl{background:#E8F8F0;color:#27AE60;}
    #epro-app-en .epro-tog input[type="radio"]:checked + .nlbl{background:#FEF0EE;color:#E74C3C;}
    #epro-app-en .epro-btns{display:flex;gap:12px;margin-top:28px;justify-content:space-between;}
    #epro-app-en .epro-btn{padding:13px 28px;border-radius:10px;font-family:'Inter',sans-serif;font-size:14px;font-weight:700;cursor:pointer;border:none;transition:all .3s;}
    #epro-app-en .epro-bp{background:linear-gradient(135deg,#1A3A6B,#2D6BC4);color:#fff;}
    #epro-app-en .epro-bp:hover{transform:translateY(-2px);box-shadow:0 6px 20px rgba(26,58,107,.3);}
    #epro-app-en .epro-bp:disabled{opacity:.6;cursor:wait;}
    #epro-app-en .epro-bs{background:#F4F7FB;border:1.5px solid var(--border);color:var(--blue);}
    #epro-app-en .epro-msg{padding:14px 18px;border-radius:9px;font-size:13px;margin-top:12px;display:none;}
    #epro-app-en .epro-msg.err{background:#FEF0EE;border:1px solid #FADBD8;color:#C0392B;display:block;}
    /* Results */
    #epro-app-en .epro-results{display:none;animation:eproFen .5s ease;}
    #epro-app-en .epro-results.active{display:block;}
    #epro-app-en .epro-rh{background:linear-gradient(135deg,#0D1F3C,#1A3A6B,#2D6BC4);border-radius:16px;margin-bottom:14px;color:#fff;overflow:hidden;}
    #epro-app-en .epro-rh-top{padding:8px 24px;background:rgba(201,168,76,.15);border-bottom:1px solid rgba(201,168,76,.3);display:flex;align-items:center;justify-content:space-between;}
    #epro-app-en .epro-rh-logo{color:#C9A84C;font-size:16px;font-weight:900;letter-spacing:2px;}
    #epro-app-en .epro-rh-url{color:rgba(255,255,255,.4);font-size:11px;}
    #epro-app-en .epro-rh-body{padding:36px;text-align:center;}
    #epro-app-en .epro-rh .cn{font-size:13px;opacity:.6;margin-bottom:8px;letter-spacing:1px;}
    #epro-app-en .epro-rh .company-title{font-size:24px;font-weight:900;color:#fff;margin-bottom:24px;}
    #epro-app-en .epro-score-ring{width:120px;height:120px;border-radius:50%;border:4px solid rgba(201,168,76,.5);display:flex;flex-direction:column;align-items:center;justify-content:center;margin:0 auto 20px;background:rgba(255,255,255,.08);box-shadow:0 0 30px rgba(201,168,76,.2);}
    #epro-app-en .epro-score-ring .sn{font-size:40px;font-weight:900;color:#fff;line-height:1;}
    #epro-app-en .epro-score-ring .sl{font-size:11px;color:rgba(255,255,255,.6);}
    #epro-app-en .epro-status-pill{display:inline-block;padding:9px 26px;border-radius:50px;font-size:15px;font-weight:800;margin-top:4px;border:2px solid rgba(255,255,255,.3);}
    #epro-app-en .epro-scores-row{display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin-bottom:14px;}
    #epro-app-en .epro-sc-card{background:#fff;border:1.5px solid var(--border);border-radius:12px;padding:20px 16px;text-align:center;}
    #epro-app-en .epro-sc-card .sc-label{font-size:12px;color:var(--muted);margin-bottom:8px;}
    #epro-app-en .epro-sc-card .sc-val{font-size:28px;font-weight:900;line-height:1;margin-bottom:8px;}
    #epro-app-en .epro-sc-card .sc-bar{height:6px;background:#EEF4FF;border-radius:10px;overflow:hidden;}
    #epro-app-en .epro-sc-card .sc-bar-fill{height:100%;border-radius:10px;transition:width 1.2s ease;}
    #epro-app-en .epro-section{background:#fff;border:1px solid var(--border);border-radius:12px;margin-bottom:12px;overflow:hidden;}
    #epro-app-en .epro-section-head{background:linear-gradient(90deg,#0D1F3C,#1A3A6B);padding:14px 20px;display:flex;align-items:center;gap:10px;}
    #epro-app-en .epro-section-head .sec-ico{font-size:18px;}
    #epro-app-en .epro-section-head .sec-title{color:#C9A84C;font-size:15px;font-weight:800;letter-spacing:.5px;}
    #epro-app-en .epro-rt{width:100%;border-collapse:collapse;}
    #epro-app-en .epro-rt th{background:#EEF4FF;padding:11px 16px;text-align:left;font-size:12px;color:var(--blue);font-weight:700;border-bottom:2px solid var(--border);}
    #epro-app-en .epro-rt td{padding:11px 16px;font-size:13px;color:var(--text);border-bottom:1px solid #F0F4FA;}
    #epro-app-en .epro-rt tr:last-child td{border-bottom:none;}
    #epro-app-en .epro-rt tr:nth-child(even) td{background:#FAFBFD;}
    #epro-app-en .epro-blockers{background:#FEF0EE;border:1px solid #FADBD8;border-left:4px solid #C0392B;border-radius:12px;padding:20px;margin-bottom:12px;}
    #epro-app-en .epro-blockers h4{color:#C0392B;font-size:15px;margin-bottom:12px;font-weight:800;}
    #epro-app-en .epro-blockers ul{list-style:none;margin:0;padding:0;}
    #epro-app-en .epro-blockers ul li{padding:6px 0;font-size:13px;color:#A93226;display:flex;align-items:center;gap:8px;border-bottom:1px solid rgba(192,57,43,.1);}
    #epro-app-en .epro-blockers ul li:last-child{border-bottom:none;}
    #epro-app-en .epro-blockers ul li::before{content:'✕';width:18px;height:18px;background:#C0392B;color:#fff;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:10px;flex-shrink:0;}
    #epro-app-en .epro-email-notice{background:linear-gradient(90deg,#EEF4FF,#F8FAFD);border:1px solid #C0D4F5;border-left:4px solid var(--gold);border-radius:10px;padding:14px 18px;font-size:13px;color:#1A3A6B;margin-bottom:12px;display:flex;align-items:center;gap:10px;}
    #epro-app-en .epro-report-footer{background:linear-gradient(135deg,#0D1F3C,#1A3A6B);border-radius:12px;padding:20px;text-align:center;margin-top:12px;}
    #epro-app-en .epro-report-footer .footer-brand{color:#C9A84C;font-size:18px;font-weight:900;letter-spacing:2px;margin-bottom:4px;}
    #epro-app-en .epro-report-footer .footer-info{color:rgba(255,255,255,.65);font-size:12px;line-height:1.7;}
    #epro-app-en .epro-print-btns{display:flex;gap:12px;margin-top:16px;justify-content:center;}
    @media(max-width:600px){#epro-app-en .epro-row{grid-template-columns:1fr;}#epro-app-en .epro-row.three{grid-template-columns:1fr 1fr;}#epro-app-en .epro-scores-row{grid-template-columns:1fr;}#epro-app-en .epro-card{padding:22px 16px;}}
    </style>

    <div id="epro-app-en">

    <!-- HERO -->
    <div class="epro-hero">
        <div class="epro-logo-bar">
            <div>
                <div class="epro-logo-text">EPRO</div>
                <div class="epro-logo-sub">BUSINESS DEVELOPMENT</div>
            </div>
        </div>
        <div class="epro-badge">Strategic Assessment Tool</div>
        <h2>IPO <span>Readiness Assessment</span></h2>
        <p>Evaluate your company accurately and transparently through a comprehensive financial and governance analysis to determine your IPO readiness.</p>
    </div>
    <div class="epro-divider"></div>

    <div class="epro-intro" id="eproIntroBoxEn">
        <p>This assessment is designed to help companies evaluate their current readiness and identify development opportunities in key areas such as governance, financial structure, operational efficiency, and strategic planning. By answering a set of straightforward questions, you will gain a quick overview of your company's current status and readiness for an IPO.</p>
        <div style="margin-top:22px;">
            <button class="epro-btn epro-bp" onclick="eproStartEn()" style="font-size:15px;padding:14px 36px;">Start Assessment →</button>
        </div>
    </div>

    <div class="epro-steps-wrap" id="eproStepsWrapEn" style="display:none;">
        <div class="epro-steps" id="eproStepsEn">
            <div class="epro-step active" data-s="0"><div class="epro-snum">1</div><div class="epro-slbl">Your Info</div></div>
            <div class="epro-step" data-s="1"><div class="epro-snum">2</div><div class="epro-slbl">Company</div></div>
            <div class="epro-step" data-s="2"><div class="epro-snum">3</div><div class="epro-slbl">Financial</div></div>
            <div class="epro-step" data-s="3"><div class="epro-snum">4</div><div class="epro-slbl">Governance</div></div>
            <div class="epro-step" data-s="4"><div class="epro-snum">5</div><div class="epro-slbl">IPO</div></div>
            <div class="epro-step" data-s="5"><div class="epro-snum">6</div><div class="epro-slbl">Results</div></div>
        </div>
    </div>

    <!-- STEP 0 -->
    <div class="epro-card" id="eproEnS0">
        <div class="epro-ct">👤 Your Information <span class="etag">Before You Begin</span></div>
        <div class="epro-cs">Your full assessment report will be sent to your email upon completion.</div>
        <div class="epro-row">
            <div class="epro-fg"><label>Full Name <span class="req">*</span></label><input type="text" id="enLeadName" placeholder="Your full name"></div>
            <div class="epro-fg"><label>Email Address <span class="req">*</span></label><input type="email" id="enLeadEmail" placeholder="email@company.com"></div>
        </div>
        <div class="epro-row one">
            <div class="epro-fg"><label>Phone Number</label><input type="tel" id="enLeadPhone" placeholder="+966 5X XXX XXXX"></div>
        </div>
        <div id="enMsg0" class="epro-msg"></div>
        <div class="epro-btns"><button class="epro-btn epro-bp" onclick="eproGoEn(1)">Next → Company Details</button></div>
    </div>

    <!-- STEP 1 -->
    <div class="epro-card" id="eproEnS1">
        <div class="epro-ct">🏢 Company Information <span class="etag">Step 1 of 4</span></div>
        <div class="epro-cs">Enter your company's basic information to begin the assessment.</div>
        <div class="epro-row">
            <div class="epro-fg"><label>Company Name <span class="req">*</span></label><input type="text" id="enCompanyName" placeholder="e.g. Horizon Investment Co."></div>
            <div class="epro-fg"><label>Sector <span class="req">*</span></label><select id="enSector"><option value="">-- Select Sector --</option><option>Financial & Banking</option><option>Technology & Telecom</option><option>Industry & Manufacturing</option><option>Real Estate & Construction</option><option>Healthcare</option><option>Energy & Petrochemicals</option><option>Retail & Consumer</option><option>Agriculture & Food</option><option>Education</option><option>Other</option></select></div>
        </div>
        <div class="epro-row">
            <div class="epro-fg"><label>Country <span class="req">*</span></label><select id="enCountry"><option value="">-- Select Country --</option><option>Saudi Arabia</option><option>United Arab Emirates</option><option>Egypt</option><option>Kuwait</option><option>Qatar</option><option>Bahrain</option><option>Oman</option><option>Jordan</option><option>Morocco</option><option>Palestine</option><option>United Kingdom</option><option>United States</option><option>Other</option></select></div>
            <div class="epro-fg"><label>Year Founded <span class="req">*</span></label><input type="number" id="enFoundedYear" placeholder="e.g. 2010" min="1900" max="2025"></div>
        </div>
        <div class="epro-row">
            <div class="epro-fg"><label>Annual Revenue <span class="req">*</span></label><input type="number" id="enAnnualRevenue" placeholder="0" step="0.01"></div>
            <div class="epro-fg"><label>Number of Employees <span class="req">*</span></label><input type="number" id="enEmployees" placeholder="0"></div>
        </div>
        <div class="epro-row one">
            <div class="epro-fg"><label>Assessment Goal <span class="req">*</span></label><select id="enGoal"><option value="">-- Select Goal --</option><option>Initial Public Offering (IPO)</option><option>Improve Governance</option><option>Secure Funding</option><option>Merger or Acquisition</option></select></div>
        </div>
        <div class="epro-btns">
            <button class="epro-btn epro-bp" onclick="eproGoEn(2)">Next → Financial Data</button>
            <button class="epro-btn epro-bs" onclick="eproGoEn(0)">← Back</button>
        </div>
    </div>

    <!-- STEP 2 -->
    <div class="epro-card" id="eproEnS2">
        <div class="epro-ct">📊 Financial Data <span class="etag">Step 2 of 4</span></div>
        <div class="epro-cs">Enter your financial data accurately – ratios will be calculated automatically.</div>
        <div class="epro-sl">Income Statement</div>
        <div class="epro-row">
            <div class="epro-fg"><label>Total Revenue – Current Year <span class="req">*</span></label><input type="number" id="enRevCurrent" placeholder="0" step="0.01"></div>
            <div class="epro-fg"><label>Total Revenue – Previous Year <span class="req">*</span></label><input type="number" id="enRevPrev" placeholder="0" step="0.01"></div>
        </div>
        <div class="epro-row">
            <div class="epro-fg"><label>Net Profit <span class="req">*</span></label><input type="number" id="enNetProfit" placeholder="0" step="0.01"></div>
            <div class="epro-fg"><label>Gross Profit <span class="req">*</span></label><input type="number" id="enGrossProfit" placeholder="0" step="0.01"></div>
        </div>
        <hr class="epro-hr">
        <div class="epro-sl">Balance Sheet</div>
        <div class="epro-row three">
            <div class="epro-fg"><label>Total Assets <span class="req">*</span></label><input type="number" id="enTotalAssets" placeholder="0" step="0.01"></div>
            <div class="epro-fg"><label>Total Liabilities <span class="req">*</span></label><input type="number" id="enTotalLiabilities" placeholder="0" step="0.01"></div>
            <div class="epro-fg"><label>Shareholders' Equity <span class="req">*</span></label><input type="number" id="enEquity" placeholder="0" step="0.01"></div>
        </div>
        <div class="epro-row three">
            <div class="epro-fg"><label>Current Assets <span class="req">*</span></label><input type="number" id="enCurrentAssets" placeholder="0" step="0.01"></div>
            <div class="epro-fg"><label>Current Liabilities <span class="req">*</span></label><input type="number" id="enCurrentLiabilities" placeholder="0" step="0.01"></div>
            <div class="epro-fg"><label>Cash & Cash Equivalents <span class="req">*</span></label><input type="number" id="enCash" placeholder="0" step="0.01"></div>
        </div>
        <div class="epro-row one">
            <div class="epro-fg"><label>Total Debt <span class="req">*</span></label><input type="number" id="enTotalDebt" placeholder="0" step="0.01"></div>
        </div>
        <div class="epro-btns">
            <button class="epro-btn epro-bp" onclick="eproGoEn(3)">Next → Governance</button>
            <button class="epro-btn epro-bs" onclick="eproGoEn(1)">← Back</button>
        </div>
    </div>

    <!-- STEP 3 -->
    <div class="epro-card" id="eproEnS3">
        <div class="epro-ct">⚖️ Governance Assessment <span class="etag">Step 3 of 4</span></div>
        <div class="epro-cs">Answer the following questions accurately for a comprehensive governance evaluation.</div>
        <?php
        $egqs=[['g1','Separation between ownership and executive management'],['g2','Internal Audit function exists'],['g3','Audit Committee in place'],['g5','Risk Management Policy'],['g6','Conflict of Interest Policy'],['g7','Disclosure Policy'],['g8','Investor Relations Policy'],['g9','Board meeting minutes documented'],['g10','Management Succession Plan']];
        echo '<div style="display:flex;flex-direction:column;gap:14px;">';
        for($i=0;$i<count($egqs);$i+=2){echo '<div class="epro-row">';foreach(array_slice($egqs,$i,2) as $q){echo '<div class="epro-fg"><label>'.$q[1].'</label><div class="epro-tog"><input type="radio" name="en_'.$q[0].'" id="en_'.$q[0].'y" value="yes"><label for="en_'.$q[0].'y" class="ylbl">✅ Yes</label><input type="radio" name="en_'.$q[0].'" id="en_'.$q[0].'n" value="no"><label for="en_'.$q[0].'n" class="nlbl">❌ No</label></div></div>';}echo '</div>';}
        echo '</div>';
        ?>
        <div class="epro-row" style="margin-top:14px;">
            <div class="epro-fg"><label>Number of Independent Board Members</label><input type="number" id="enIndMembers" placeholder="0" min="0"></div>
        </div>
        <div class="epro-btns">
            <button class="epro-btn epro-bp" onclick="eproGoEn(4)">Next → IPO Readiness</button>
            <button class="epro-btn epro-bs" onclick="eproGoEn(2)">← Back</button>
        </div>
    </div>

    <!-- STEP 4 -->
    <div class="epro-card" id="eproEnS4">
        <div class="epro-ct">🚀 IPO Readiness <span class="etag">Step 4 of 4</span></div>
        <div class="epro-cs">Questions about your structural readiness for listing on a financial market.</div>
        <?php
        $eiqs=[['ipo1','Do you have audited financial statements for 2+ years?'],['ipo2','Do you apply IFRS accounting standards?'],['ipo3','Are your financial statements consolidated?'],['ipo4','Are there independent members on the board?'],['ipo5','Is there an Audit Committee?'],['ipo6','Is there an approved Disclosure Policy?'],['ipo7','Is there an Investor Relations Policy?'],['ipo8','Are operating cash flows positive?']];
        echo '<div style="display:flex;flex-direction:column;gap:14px;">';
        for($i=0;$i<count($eiqs);$i+=2){echo '<div class="epro-row">';foreach(array_slice($eiqs,$i,2) as $q){echo '<div class="epro-fg"><label>'.$q[1].'</label><div class="epro-tog"><input type="radio" name="en_'.$q[0].'" id="en_'.$q[0].'y" value="yes"><label for="en_'.$q[0].'y" class="ylbl">✅ Yes</label><input type="radio" name="en_'.$q[0].'" id="en_'.$q[0].'n" value="no"><label for="en_'.$q[0].'n" class="nlbl">❌ No</label></div></div>';}echo '</div>';}
        echo '</div>';
        ?>
        <div id="enMsg4" class="epro-msg"></div>
        <div class="epro-btns">
            <button class="epro-btn epro-bp" id="enSubBtn" onclick="eproSubmitEn()">🔍 Submit & View Results</button>
            <button class="epro-btn epro-bs" onclick="eproGoEn(3)">← Back</button>
        </div>
    </div>

    <!-- RESULTS -->
    <div class="epro-results" id="eproEnResults"></div>

    </div>

    <script>
    var eCen=0,eAen='<?=esc_js($ajax)?>',eNen='<?=esc_js($nonce)?>';
    function eUSen(s){document.querySelectorAll('#eproStepsEn .epro-step').forEach(function(e){var n=parseInt(e.dataset.s);e.classList.remove('active','done');if(n===s)e.classList.add('active');else if(n<s)e.classList.add('done');});}
    function eVen(s){
        if(s===0){var n=document.getElementById('enLeadName').value.trim(),em=document.getElementById('enLeadEmail').value.trim();if(!n||!em){var m=document.getElementById('enMsg0');m.className='epro-msg err';m.textContent='⚠️ Please enter your name and email address.';return false;}}
        if(s===1){var ids=['enCompanyName','enSector','enCountry','enFoundedYear','enAnnualRevenue','enEmployees','enGoal'];for(var i=0;i<ids.length;i++){if(!document.getElementById(ids[i]).value.trim()){alert('⚠️ Please fill in all required fields.');document.getElementById(ids[i]).focus();return false;}}}
        if(s===2){var ids2=['enRevCurrent','enRevPrev','enNetProfit','enGrossProfit','enTotalAssets','enTotalLiabilities','enEquity','enCurrentAssets','enCurrentLiabilities','enCash','enTotalDebt'];for(var j=0;j<ids2.length;j++){if(document.getElementById(ids2[j]).value===''){alert('⚠️ Please fill in all financial fields.');document.getElementById(ids2[j]).focus();return false;}}}
        if(s===3){var gf=['en_g1','en_g2','en_g3','en_g5','en_g6','en_g7','en_g8','en_g9','en_g10'];for(var g=0;g<gf.length;g++){if(!document.querySelector('input[name="'+gf[g]+'"]:checked')){alert('⚠️ Please answer all governance questions.');return false;}}}
        if(s===4){var ip=['en_ipo1','en_ipo2','en_ipo3','en_ipo4','en_ipo5','en_ipo6','en_ipo7','en_ipo8'];for(var p=0;p<ip.length;p++){if(!document.querySelector('input[name="'+ip[p]+'"]:checked')){alert('⚠️ Please answer all IPO readiness questions.');return false;}}}
        return true;
    }
    function eproStartEn(){
        document.getElementById('eproIntroBoxEn').style.display='none';
        document.getElementById('eproStepsWrapEn').style.display='block';
        document.getElementById('eproEnS0').classList.add('active');
        window.scrollTo({top:0,behavior:'smooth'});
    }
    function eproGoEn(n){
        if(n>eCen&&!eVen(eCen))return;
        var cs=['eproEnS0','eproEnS1','eproEnS2','eproEnS3','eproEnS4'];
        if(cs[eCen])document.getElementById(cs[eCen]).classList.remove('active');
        if(cs[n])document.getElementById(cs[n]).classList.add('active');
        eCen=n;eUSen(n);window.scrollTo({top:0,behavior:'smooth'});
    }
    function eGRen(name){var el=document.querySelector('input[name="'+name+'"]:checked');return el?el.value:'no';}
    function eproSubmitEn(){
        var btn=document.getElementById('enSubBtn');
        btn.disabled=true;btn.textContent='⏳ Processing...';
        var fd=new FormData();fd.append('action','epro_submit_en');fd.append('nonce',eNen);
        var d={leadName:document.getElementById('enLeadName').value,leadEmail:document.getElementById('enLeadEmail').value,leadPhone:document.getElementById('enLeadPhone').value,companyName:document.getElementById('enCompanyName').value,sector:document.getElementById('enSector').value,country:document.getElementById('enCountry').value,foundedYear:document.getElementById('enFoundedYear').value,annualRevenue:document.getElementById('enAnnualRevenue').value,employees:document.getElementById('enEmployees').value,assessmentGoal:document.getElementById('enGoal').value,revCurrent:document.getElementById('enRevCurrent').value,revPrev:document.getElementById('enRevPrev').value,netProfit:document.getElementById('enNetProfit').value,grossProfit:document.getElementById('enGrossProfit').value,totalAssets:document.getElementById('enTotalAssets').value,totalLiabilities:document.getElementById('enTotalLiabilities').value,equity:document.getElementById('enEquity').value,currentAssets:document.getElementById('enCurrentAssets').value,currentLiabilities:document.getElementById('enCurrentLiabilities').value,cash:document.getElementById('enCash').value,totalDebt:document.getElementById('enTotalDebt').value};
        var gov={g1:eGRen('en_g1'),g2:eGRen('en_g2'),g3:eGRen('en_g3'),g5:eGRen('en_g5'),g6:eGRen('en_g6'),g7:eGRen('en_g7'),g8:eGRen('en_g8'),g9:eGRen('en_g9'),g10:eGRen('en_g10'),independentMembers:document.getElementById('enIndMembers').value||0};
        var ipo={ipo1:eGRen('en_ipo1'),ipo2:eGRen('en_ipo2'),ipo3:eGRen('en_ipo3'),ipo4:eGRen('en_ipo4'),ipo5:eGRen('en_ipo5'),ipo6:eGRen('en_ipo6'),ipo7:eGRen('en_ipo7'),ipo8:eGRen('en_ipo8')};
        for(var k in d)fd.append('data['+k+']',d[k]);
        for(var gk in gov)fd.append('data[governance]['+gk+']',gov[gk]);
        for(var ik in ipo)fd.append('data[ipo]['+ik+']',ipo[ik]);
        fetch(eAen,{method:'POST',body:fd}).then(function(r){return r.json();}).then(function(res){
            if(res.success){eproSRen(res.data,d.companyName,d.leadEmail);}
            else{var m=document.getElementById('enMsg4');m.className='epro-msg err';m.textContent='An error occurred. Please try again.';btn.disabled=false;btn.textContent='🔍 Submit & View Results';}
        }).catch(function(){var m=document.getElementById('enMsg4');m.className='epro-msg err';m.textContent='Connection error. Please try again.';btn.disabled=false;btn.textContent='🔍 Submit & View Results';});
    }
    function pctEn(v){return isFinite(v)&&v!=-999?v.toFixed(1)+'%':'—';}
    function rtoEn(v){return isFinite(v)&&v!=999?v.toFixed(2)+'x':'—';}
    function gCLen(s){return s>=80?'#27AE60':s>=65?'#D4AC0D':s>=50?'#E67E22':'#E74C3C';}
    function gRatingEn(emoji){
        if(emoji==='🟢')return '<span style="color:#27AE60;font-size:14px;">●</span> <span style="color:#27AE60;font-size:12px;font-weight:700;">Excellent</span>';
        if(emoji==='🟡')return '<span style="color:#D4AC0D;font-size:14px;">●</span> <span style="color:#D4AC0D;font-size:12px;font-weight:700;">Acceptable</span>';
        return '<span style="color:#E74C3C;font-size:14px;">●</span> <span style="color:#E74C3C;font-size:12px;font-weight:700;">Weak</span>';
    }
    function eproSRen(data, companyName, email) {
        document.getElementById('eproEnS4').classList.remove('active');
        eUSen(5);
        var d=data,r=d.ratios,c=d.classifications,ib=d.isBlocked;
        var ov=d.overall,fin=d.finScore,gs=d.govScore,is=d.ipoScore;
        var fColor=gCLen(fin),gColor=gCLen(gs),iColor=ib?'#C0392B':gCLen(is);
        var statusText=ib?'IPO Blocked':(ov>=80?'Excellent – IPO Ready':ov>=65?'Good – Minor Improvements Needed':ov>=50?'Average – Restructuring Required':'Weak – High Risk');
        var statusBg=ib?'rgba(192,57,43,.2)':(ov>=80?'rgba(39,174,96,.2)':ov>=65?'rgba(212,172,13,.2)':ov>=50?'rgba(230,126,34,.2)':'rgba(231,76,60,.2)');
        var statusColor=ib?'#FFAAAA':(ov>=80?'#7FD4A8':ov>=65?'#F8E07A':ov>=50?'#F5B77A':'#FFAAAA');

        var h = `
        <div class="epro-rh">
            <div class="epro-rh-top">
                <span class="epro-rh-logo">EPRO</span>
                <span class="epro-rh-url">eprome.com</span>
            </div>
            <div class="epro-rh-body">
                <div class="cn">IPO READINESS ASSESSMENT REPORT</div>
                <div class="company-title">${companyName}</div>
                <div class="epro-score-ring">
                    <div class="sn">${ov}</div>
                    <div class="sl">out of 100</div>
                </div>
                <div class="epro-status-pill" style="background:${statusBg};color:${statusColor};">${statusText}</div>
            </div>
        </div>

        <div class="epro-email-notice">
            <span style="font-size:20px;">📧</span>
            <span>A full copy of this report has been sent to: <strong>${email}</strong></span>
        </div>

        <div class="epro-scores-row">
            <div class="epro-sc-card">
                <div class="sc-label">Financial Health</div>
                <div class="sc-val" style="color:${fColor}">${fin}</div>
                <div class="sc-bar"><div class="sc-bar-fill" style="width:${fin}%;background:${fColor}"></div></div>
            </div>
            <div class="epro-sc-card">
                <div class="sc-label">Governance</div>
                <div class="sc-val" style="color:${gColor}">${gs}</div>
                <div class="sc-bar"><div class="sc-bar-fill" style="width:${gs}%;background:${gColor}"></div></div>
            </div>
            <div class="epro-sc-card">
                <div class="sc-label">IPO Readiness</div>
                <div class="sc-val" style="color:${iColor};font-size:${ib?'13px':'28px'}">${ib?'<span style="display:inline-block;background:#C0392B;color:#fff;padding:3px 10px;border-radius:6px;font-size:12px;font-weight:800;">BLOCKED</span>':is}</div>
                <div class="sc-bar"><div class="sc-bar-fill" style="width:${ib?0:is}%;background:${iColor}"></div></div>
            </div>
        </div>`;

        if(ib && d.blockers && d.blockers.length>0){
            h += `<div class="epro-blockers"><h4>IPO Blockers</h4><ul>`;
            d.blockers.forEach(b => h += `<li>${b}</li>`);
            h += `</ul></div>`;
        }

        h += `
        <div class="epro-section">
            <div class="epro-section-head"><span class="sec-ico">📈</span><span class="sec-title">Financial Ratios Detail</span></div>
            <div class="epro-section-body">
            <table class="epro-rt">
                <thead><tr>
                    <th>Indicator</th><th style="text-align:center;">Value</th><th style="text-align:center;">Rating</th>
                </tr></thead><tbody>`;

        var rows=[
            ['Net Profit Margin',pctEn(r.netMargin),c.netMargin.emoji],
            ['Return on Equity (ROE)',pctEn(r.roe),c.roe.emoji],
            ['Return on Assets (ROA)',pctEn(r.roa),c.roa.emoji],
            ['Gross Profit Margin',pctEn(r.grossMargin),c.grossMargin.emoji],
            ['Revenue Growth Rate',pctEn(r.revGrowth),c.revGrowth.emoji],
            ['Current Ratio',rtoEn(r.currentRatio),c.currentRatio.emoji],
            ['Cash Ratio',rtoEn(r.cashRatio),c.cashRatio.emoji],
            ['Debt-to-Equity',rtoEn(r.debtToEquity),c.debtToEquity.emoji],
            ['Debt-to-Assets',pctEn(r.debtToAsset),c.debtToAsset.emoji],
            ['Equity-to-Assets',pctEn(r.equityToAsset),c.equityToAsset.emoji]
        ];
        rows.forEach(rw => {
            h += `<tr><td>${rw[0]}</td><td style="text-align:center;font-weight:700;color:#1A3A6B;">${rw[1]}</td><td style="text-align:center;">${gRatingEn(rw[2])}</td></tr>`;
        });

        h += `</tbody></table></div></div>

        <div class="epro-report-footer">
            <div class="footer-brand">EPRO</div>
            <div class="footer-info">
                info@eprome.com &nbsp;|&nbsp; www.eprome.com<br>
                Building No. 3630, 2nd Floor, Al Urubah Street, Al Wurud District, Riyadh 12252, KSA<br>
                +966 56 630 0876
            </div>
        </div>

        <div class="epro-print-btns">
            <button class="epro-btn epro-bs" onclick="eproPrintEn()">🖨️ Print Report PDF</button>
            <button class="epro-btn epro-bp" onclick="location.reload()">Assess Another Company</button>
        </div>`;

        var el=document.getElementById('eproEnResults');
        el.innerHTML=h; el.classList.add('active');
        window.scrollTo({top:0,behavior:'smooth'});
    }

    function eproPrintEn() {
        var el = document.getElementById('eproEnResults');
        var clone = el.cloneNode(true);
        clone.querySelectorAll('.epro-print-btns,.epro-email-notice').forEach(function(n){ n.remove(); });

        // Fix sc-val: rewrite innerHTML directly to remove oversized inline font-size
        clone.querySelectorAll('.epro-sc-card').forEach(function(card){
            var val = card.querySelector('.sc-val');
            if(!val) return;
            var rawText = (val.innerText || val.textContent || '').trim();
            var color   = val.style.color || '#1A3A6B';
            var isBlocked = rawText.indexOf('BLOCKED') !== -1 || rawText === '⛔';
            val.removeAttribute('style');
            val.style.marginBottom = '4px';
            if(isBlocked){
                val.innerHTML = '<span style="display:inline-block;background:#C0392B;color:#fff;padding:2px 8px;border-radius:4px;font-size:9px;font-weight:800;">BLOCKED</span>';
            } else {
                val.innerHTML = '<span style="font-size:20px;font-weight:900;color:'+color+';">' + rawText + '</span>';
            }
        });

        // Fix rating dots -> text badges
        clone.querySelectorAll('td').forEach(function(td){
            td.innerHTML = td.innerHTML
                .replace(/<span[^>]*color:#27AE60[^>]*>●<\/span>\s*<span[^>]*>Excellent<\/span>/g,'<span style="padding:2px 6px;border-radius:3px;background:#E8F8F0;color:#27AE60;font-size:8px;font-weight:700;">✓ Excellent</span>')
                .replace(/<span[^>]*color:#D4AC0D[^>]*>●<\/span>\s*<span[^>]*>Acceptable<\/span>/g,'<span style="padding:2px 6px;border-radius:3px;background:#FEF9E7;color:#D4AC0D;font-size:8px;font-weight:700;">~ Acceptable</span>')
                .replace(/<span[^>]*color:#E74C3C[^>]*>●<\/span>\s*<span[^>]*>Weak<\/span>/g,'<span style="padding:2px 6px;border-radius:3px;background:#FEF0EE;color:#E74C3C;font-size:8px;font-weight:700;">✕ Weak</span>');
        });

        var css = `
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;700;900&display=swap');
        @page { margin: 12mm 14mm; size: A4 portrait; }
        /* Kill oversized emoji images in print */
        img { max-width: 0 !important; max-height: 0 !important; display: none !important; }
        .epro-score-ring .sn, .sc-val span { font-size: inherit !important; }
        * { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; box-sizing: border-box; }
        body { font-family: 'Inter', Arial, sans-serif; direction: ltr; font-size: 11px; line-height: 1.5; color: #1A2B4A; background: #fff; margin: 0; padding: 0; }
        /* ── Header ── */
        .epro-rh { background: #1A3A6B !important; border-radius: 8px; overflow: hidden; margin-bottom: 12px; }
        .epro-rh-top { background: rgba(201,168,76,.25) !important; padding: 6px 14px; display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid rgba(201,168,76,.3); }
        .epro-rh-logo { color: #C9A84C; font-size: 13px; font-weight: 900; letter-spacing: 2px; }
        .epro-rh-url { color: rgba(255,255,255,.5); font-size: 9px; }
        .epro-rh-body { padding: 14px 16px; text-align: center; }
        .epro-rh .cn { font-size: 9px; color: rgba(255,255,255,.65); letter-spacing: 1px; margin-bottom: 4px; }
        .epro-rh .company-title { font-size: 17px; font-weight: 900; color: #fff; margin-bottom: 10px; }
        .epro-score-ring { width: 64px; height: 64px; border-radius: 50%; border: 2px solid rgba(201,168,76,.5); display: flex; flex-direction: column; align-items: center; justify-content: center; margin: 0 auto 10px; background: rgba(255,255,255,.1) !important; }
        .epro-score-ring .sn { font-size: 22px; font-weight: 900; color: #fff; line-height: 1; }
        .epro-score-ring .sl { font-size: 8px; color: rgba(255,255,255,.6); margin-top: 2px; }
        .epro-status-pill { display: inline-block; padding: 4px 14px; border-radius: 20px; font-size: 10px; font-weight: 800; border: 1px solid rgba(255,255,255,.35); }
        /* ── 3-Score Cards ── */
        .epro-scores-row { display: grid !important; grid-template-columns: repeat(3,1fr) !important; gap: 8px; margin-bottom: 12px; }
        .epro-sc-card { background: #F8FAFD !important; border: 1px solid #D0DCF0; border-radius: 6px; padding: 10px 8px; text-align: center; }
        .epro-sc-card .sc-label { font-size: 9px; color: #6B7FA3; margin-bottom: 4px; }
        .epro-sc-card .sc-val { font-size: 16px !important; font-weight: 900; line-height: 1.2; margin-bottom: 4px; }
        .epro-sc-card .sc-val * { font-size: 10px !important; }
        .epro-sc-card .sc-bar { height: 4px; background: #EEF4FF; border-radius: 4px; overflow: hidden; margin-top: 4px; }
        .epro-sc-card .sc-bar-fill { height: 100%; border-radius: 4px; }
        /* ── Kill oversized ── */
        [style*="font-size:48px"],[style*="font-size: 48px"],[style*="font-size:36px"],[style*="font-size: 36px"],[style*="font-size:28px"],[style*="font-size: 28px"] { font-size: 16px !important; }
        /* ── Blockers ── */
        .epro-blockers { background: #FEF0EE !important; border: 1px solid #FADBD8; border-left: 3px solid #C0392B; border-radius: 6px; padding: 10px 12px; margin-bottom: 12px; }
        .epro-blockers h4 { color: #C0392B; font-size: 11px; margin: 0 0 6px; font-weight: 800; }
        .epro-blockers ul { list-style: none; margin: 0; padding: 0; }
        .epro-blockers ul li { padding: 3px 0; font-size: 10px; color: #A93226; display: flex; align-items: center; gap: 6px; border-bottom: 1px solid rgba(192,57,43,.1); }
        .epro-blockers ul li:last-child { border-bottom: none; }
        .epro-blockers ul li::before { content: 'x'; width: 12px; height: 12px; background: #C0392B; color: #fff; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-size: 7px; flex-shrink: 0; }
        /* ── Sections ── */
        .epro-section { background: #fff; border: 1px solid #D0DCF0; border-radius: 6px; margin-bottom: 12px; overflow: hidden; }
        .epro-section-head { background: #1A3A6B !important; padding: 7px 12px; display: flex; align-items: center; gap: 6px; }
        .epro-section-head .sec-ico { font-size: 11px; }
        .epro-section-head .sec-title { color: #C9A84C; font-size: 10px; font-weight: 800; letter-spacing: .5px; }
        /* ── Table ── */
        .epro-rt { width: 100%; border-collapse: collapse; }
        .epro-rt th { background: #EEF4FF !important; padding: 7px 10px; text-align: left; font-size: 9.5px; color: #1A3A6B; font-weight: 700; border-bottom: 1.5px solid #D0DCF0; }
        .epro-rt td { padding: 6px 10px; font-size: 10px; color: #1A2B4A; border-bottom: 1px solid #F0F4FA; }
        .epro-rt tr:last-child td { border-bottom: none; }
        .epro-rt tr:nth-child(even) td { background: #FAFBFD !important; }
        .epro-rt td span { font-size: 9px !important; padding: 2px 6px !important; }
        /* ── Footer ── */
        .epro-report-footer { background: #1A3A6B !important; border-radius: 6px; padding: 10px 14px; text-align: center; margin-top: 12px; }
        .epro-report-footer .footer-brand { color: #C9A84C; font-size: 12px; font-weight: 900; letter-spacing: 2px; margin-bottom: 3px; }
        .epro-report-footer .footer-info { color: rgba(255,255,255,.7); font-size: 9px; line-height: 1.6; }
        .epro-email-notice { display: none !important; }
        `;

        var fullHTML = `<!DOCTYPE html>
        <html dir="ltr">
        <head>
            <meta charset="UTF-8">
            <title>IPO Readiness Assessment Report | EPRO</title>
            <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;700;900&display=swap" rel="stylesheet">
            <style>${css}</style>
        </head>
        <body>${clone.innerHTML}</body>
        </html>`;

        var blob = new Blob([fullHTML], {type:'text/html'});
        var url = URL.createObjectURL(blob);
        var w = window.open(url, '_blank');
        if(w) w.onload = () => setTimeout(() => w.print(), 800);
    }
    </script>
    <?php
    return ob_get_clean();
}
