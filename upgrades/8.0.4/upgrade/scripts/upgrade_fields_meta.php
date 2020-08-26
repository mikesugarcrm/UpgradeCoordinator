<?php
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
 global $db;
 $query = "SELECT * FROM fields_meta_data";
 $result = $db->query($query);
 while ($row = $db->fetchByAssoc($result)) {
		$update_query = "UPDATE fields_meta_data SET ";
		$update_query .= "vname = '{$row['label']}' ";
		$update_query .= ", type = '{$row['data_type']}' ";
		$len = 50;
		if(!empty($row['max_size']))
			$len = $row['max_size'];
		$update_query .= ", len = {$len} ";
		$required = 0;
		if($row['required_option'] == 'required')
			$required = 1;
		$update_query .= ", required = {$required} ";
		$update_query .= ", massupdate = {$row['mass_update']}";
		$update_query .= " WHERE id = '{$row['id']}'";
		$db->query($update_query);
 }
?>
