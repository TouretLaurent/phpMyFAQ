<?php
/**
 * This is the page there a user can add a FAQ record.
 *
 * @package    phpMyFAQ
 * @subpackage Frontend
 * @author     Thorsten Rinne <thorsten@phpmyfaq.de>
 * @since      2002-09-16
 * @version    SVN: $Id$
 * @copyright  2002-2009 phpMyFAQ Team
 *
 * The contents of this file are subject to the Mozilla Public License
 * Version 1.1 (the "License"); you may not use this file except in
 * compliance with the License. You may obtain a copy of the License at
 * http://www.mozilla.org/MPL/
 *
 * Software distributed under the License is distributed on an "AS IS"
 * basis, WITHOUT WARRANTY OF ANY KIND, either express or implied. See the
 * License for the specific language governing rights and limitations
 * under the License.
 */

if (!defined('IS_VALID_PHPMYFAQ')) {
    header('Location: http://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['SCRIPT_NAME']));
    exit();
}

$captcha = new PMF_Captcha($sids);

if (isset($_GET['gen'])) {
    $captcha->showCaptchaImg();
    exit;
}

$faqsession->userTracking('new_entry', 0);

$question    = '';
$readonly    = '';
$question_id = PMF_Filter::filterInput(INPUT_GET, 'question', FILTER_VALIDATE_INT);
if (!is_null($question_id)) {
    $oQuestion = $faq->getQuestion($question_id);
    $question  = $oQuestion['question'];
    if (strlen($question)) {
        $readonly = ' readonly="readonly"';
    }
}

$category_id = PMF_Filter::filterInput(INPUT_GET, 'cat', FILTER_VALIDATE_INT);
if (!is_null($category_id)) {
    $categories = array(array(
        'category_id'   => $category_id,
        'category_lang' => $LANGCODE));
} else {
    $categories = array();
}

$category->buildTree();

$tpl->processTemplate('writeContent', array(
    'msgNewContentHeader'   => $PMF_LANG['msgNewContentHeader'],
    'msgNewContentAddon'    => $PMF_LANG['msgNewContentAddon'],
    'writeSendAdress'       => '?' . $sids . 'action=save',
    'defaultContentMail'    => ($user instanceof PMF_User_CurrentUser) ? $user->getUserData('email') : '',
    'defaultContentName'    => ($user instanceof PMF_User_CurrentUser) ? $user->getUserData('display_name') : '',
    'msgNewContentName'     => $PMF_LANG['msgNewContentName'],
    'msgNewContentMail'     => $PMF_LANG['msgNewContentMail'],
    'msgNewContentCategory' => $PMF_LANG['msgNewContentCategory'],
    'printCategoryOptions'  => $category->printCategoryOptions($categories),
    'msgNewContentTheme'    => $PMF_LANG['msgNewContentTheme'],
    'readonly'              => $readonly,
    'printQuestion'         => $question,
    'msgNewContentArticle'  => $PMF_LANG['msgNewContentArticle'],
    'msgNewContentKeywords' => $PMF_LANG['msgNewContentKeywords'],
    'msgNewContentLink'     => $PMF_LANG['msgNewContentLink'],
    'captchaFieldset'       => printCaptchaFieldset($PMF_LANG['msgCaptcha'], $captcha->printCaptcha('add'), $captcha->caplength),
    'msgNewContentSubmit'   => $PMF_LANG['msgNewContentSubmit']));

$tpl->includeTemplate('writeContent', 'index');
