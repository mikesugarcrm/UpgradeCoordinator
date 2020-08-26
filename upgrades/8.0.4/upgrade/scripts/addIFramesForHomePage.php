<?php
if(!defined('sugarEntry'))define('sugarEntry', true);

/**
 * This script executes after the files are copied during the install.
 *
 * LICENSE: The contents of this file are subject to the SugarCRM Professional
 * End User License Agreement ("License") which can be viewed at
 * http://www.sugarcrm.com/EULA.  By installing or using this file, You have
 * unconditionally agreed to the terms and conditions of the License, and You
 * may not use this file except in compliance with the License.  Under the
 * terms of the license, You shall not, among other things: 1) sublicense,
 * resell, rent, lease, redistribute, assign or otherwise transfer Your
 * rights to the Software, and 2) use the Software for timesharing or service
 * bureau purposes such as hosting the Software for commercial gain and/or for
 * the benefit of a third party.  Use of the Software may be subject to
 * applicable fees and any use of the Software without first paying applicable
 * fees is strictly prohibited.  You do not have the right to remove SugarCRM
 * copyrights from the source code or user interface.
 *
 * All copies of the Covered Code must include on each user interface screen:
 *  (i) the "Powered by SugarCRM" logo and
 *  (ii) the SugarCRM copyright notice
 * in the same form as they appear in the distribution.  See full license for
 * requirements.
 *
 * Your Warranty, Limitations of liability and Indemnity are expressly stated
 * in the License.  Please refer to the License for the specific language
 * governing these rights and limitations under the License.  Portions created
 * by SugarCRM are Copyright (C) 2005 SugarCRM, Inc.; All Rights Reserved.
 *
 * $Id$
 */
function add_new_iframe_dashlets(){
	$hasDiscoverSugarPro = false;
	$hasSugarNews = false;
	$newDashlets = array();
	$newIds = array();
	require_once('cache/dashlets/dashlets.php');
    $sugarNewsDashlet = array('className' => 'iFrameDashlet',
					 'module' => 'iFrames',
					 'fileLocation' => $dashletsFiles['iFrameDashlet']['file'],
					 'options' => array('title' => translate('LBL_DASHLET_SUGAR_NEWS','iFrames'),
                    'url' => 'http://apps.sugarcrm.com/dashlet/5.2.0/sugarcrm-news-dashlet.html?lang=@@LANG@@&edition=@@EDITION@@&ver=@@VER@@',
                    'height' => 315,
     ));

	$db = DBManagerFactory::getInstance();
	$query = "SELECT id, contents, assigned_user_id FROM user_preferences WHERE deleted = 0 AND category = 'Home'";
	$result = $db->query($query, true, "Unable to update new default dashlets! ");
	while ($row = $db->fetchByAssoc($result)) {
		$content = unserialize(base64_decode($row['contents']));
		$assigned_user_id = $row['assigned_user_id'];
		$record_id = $row['id'];

		$current_user = new User();
        $current_user->retrieve($row['assigned_user_id']);

		if(!empty($content['dashlets']) && !empty($content['pages'])){
			$originalDashlets = $content['dashlets'];
			$originalPages = $content['pages'];
			//Determine if the original perference has already had the two dashlets or not
			foreach($originalDashlets as $ds){
				if(!empty($ds['options']['title']) && $ds['options']['title'] == translate('LBL_DASHLET_SUGAR_NEWS','iFrames')){
					$hasSugarNews = true;
				}
			}

			//If the user_perference has no 'Sugar News' dashlet and no 'Discover sugar Pro' dashlet, we should add them
			if(!$hasSugarNews && !$hasDiscoverSugarPro){
				$sugarNewsId = create_guid();
				$newDashlets["$sugarNewsId"]  = $sugarNewsDashlet;
				$originalDashlets = array_merge_recursive($newDashlets, $originalDashlets );
				if( !empty($originalPages[0]['numColumns']) && !empty($originalPages[0]['columns']) ){
					switch($originalPages[0]['numColumns']){
						case '1':
							if(!empty($originalPages[0]['columns'][0]['dashlets'])){
								array_unshift($originalPages[0]['columns'][0]['dashlets'] , $sugarNewsId);
							}
							break;
						case '2':
						case '3':
							if(!empty($originalPages[0]['columns'][1]['dashlets'])){
								array_unshift($originalPages[0]['columns'][1]['dashlets'] , $sugarNewsId);
							}
					}
				}
			}
			$current_user->setPreference('dashlets', $originalDashlets, 0, 'Home');
			$current_user->setPreference('pages', $originalPages, 0, 'Home');
		}
	}
}
?>
