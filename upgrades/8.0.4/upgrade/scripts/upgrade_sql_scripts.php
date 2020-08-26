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

require_once('include/utils.php');
require_once('include/database/DBManager.php');
require_once('include/database/DBManagerFactory.php');
require_once("include/entryPoint.php");
require_once("modules/Emails/Email.php");



function upgrade_sql_scripts($return_query = false){
global $sugar_config;

$createSchema = array(
				  'IMPORT_MAPS'        	 =>array('createTable'=>false,
												  'addColumns' =>array(
												  					   array('name'=>'enclosure','type'=>'varchar','length'=>'1','null'=>'no','default'=>''),
                  													   array('name'=>'delimiter','type'=>'varchar','length'=>'1','null'=>'no','default'=>','),
                  													   array('name'=>'default_values','type'=>'blob','length'=>'','null'=>'','default'=>''),
                                                                       ),
												  'modifyColumns'=>array('name','text'),
												  ),
				  'INBOUND_EMAIL'        =>array('createTable'=>false,
												  'modifyColumns'=>array(
																		 array('name'=>'mailbox','type'=>'text','length'=>'','null'=>'no')
																		)
												  ),
				  'PROJECT_TASK'         =>array('createTable'=>false,
												  'addColumns' =>array(
												  					   array('name'=>'status','type'=>'varchar','length'=>'255','null'=>'yes','default'=>''),
                                                                       array('name'=>'order_number','type'=>'varchar','length'=>'1','null'=>'no','default'=>''),
                                                                       array('name'=>'task_number','type'=>'varchar','length'=>'1','null'=>'no','default'=>''),
                                                                       array('name'=>'estimated_effort','type'=>'varchar','length'=>'1','null'=>'no','default'=>''),
                                                                       array('name'=>'utilization','type'=>'varchar','length'=>'1','null'=>'no','default'=>''),
                                                                       )
												  ),
				  'REPORTS_CACHE'     	  =>array('createTable'=>true,
                                                   'newColumns' =>array(
												  					   array('name'=>'id','type'=>'char','length'=>'36','null'=>'no','default'=>''),
                                                                       array('name'=>'assigned_user_id','type'=>'char','length'=>'36','null'=>'no','default'=>''),
                                                                       array('name'=>'contents','type'=>'text','length'=>'','null'=>'','default'=>''),
                                                                       array('name'=>'deleted','type'=>'varchar','length'=>'1','null'=>'no','default'=>''),
                                                                       array('name'=>'date_entered','type'=>'datetime','length'=>'','null'=>'no','default'=>''),
                                                                       array('name'=>'date_modified','type'=>'datetime','length'=>'','null'=>'no','default'=>''),
                                                                       ),
                                                    'primaryKey' => array('id','assigned_user_id','deleted')
                                                 ),
                  'SAVED_REPORTS'        =>array('createTable'=>false,
												  'modifyColumns'=>array(
																		 array('name'=>'name','type'=>'varchar','length'=>'255','null'=>'no')
																		)
												  ),
				  'TRACKER'     	     =>array('createTable'=>false,
												  'addColumns' =>array(
												  					   array('name'=>'monitor_id','type'=>'char','length'=>'36','null'=>'no','default'=>''),
                                                                       array('name'=>'team_id','type'=>'varchar','length'=>'36','null'=>'','default'=>''),
                                                                       array('name'=>'deleted','type'=>'tinyint','length'=>'1','null'=>'','default'=>'0'),
                                                                       ),
												  'modifyColumns'=>array(
																		 array('name'=>'session_id','type'=>'varchar','length'=>'36','null'=>'','default'=>'NULL')
																		)
												  ),
				  'TRACKER_PERF'     	 =>array('createTable'=>true,
                                                   'newColumns' =>array(
												  					   array('name'=>'id','type'=>'int','length'=>'11','null'=>'no','default'=>'','auto_increment'=>'yes'),
                                                                       array('name'=>'monitor_id','type'=>'char','length'=>'36','null'=>'no'),
                                                                       array('name'=>'server_response_time','type'=>'double','default'=>'null'),
                                                                       array('name'=>'db_round_trips','type'=>'int','length'=>'6','default'=>'null'),
                                                                       array('name'=>'files_opened','type'=>'int','length'=>'6','default'=>'null'),
                                                                       array('name'=>'memory_usage','type'=>'int','length'=>'12','default'=>'null'),
                                                                       array('name'=>'deleted','type'=>'tinyint','length'=>'1','default'=>'0'),
                                                                       array('name'=>'date_modified','type'=>'datetime','default'=>'NULL')
                                                                       ),
                                                    'primaryKey' => array('id')

                                                 ),


				  'EMAILS_PROJECT_TASKS'  =>array('dropTable'=>false,'indices'=>array('IDX_EPT_EMAIL','IDX_EPT_PROJECT_TASK')),
				  'MEETINGS'  			  =>array('dropTable'=>false,'indices'=>array('idx_meetings_status_d','IDX_MEET_TEAM_USER_DEL','idx_notes_teamid')),
				  'PROJECT_TASK'	      =>array('dropTable'=>false,'columns'=>array('time_due','status','date_due','parent_id','order_number','task_number','depends_on_id','estimated_effort','utilization','time_start_backed')),
				  'FIELDS_META_DATA'	  =>array('dropTable'=>false,'columns'=>array('label','data_type','required_option','max_size','mass_update')),
				  'PROSPECTS'			  =>array('dropTable'=>false,'columns'=>array('email1','email2','invalid_email','email_opt_out')),
                  'CONTACTS' =>array('dropTable'=>false,
                           'columns' =>array('email1','email2',
                                             'invalid_email','email_opt_out'),
                           'indices'=>array('IDX_CONTACT_DEL_TEAM',
                                            'IDX_CONT_EMAIL1','IDX_CONT_EMAIL2',
                                            'idx_cont_first_last')),
                  'LEADS' =>array('dropTable'=>false,
                        'columns' =>array('email1','email2','invalid_email','email_opt_out'),
                        'indices'=>array('IDX_LEAD_EMAIL1','IDX_LEAD_EMAIL2',
                                         'idx_lead_first_last')),
				  'ACCOUNTS'			  =>array('dropTable'=>false,'columns'=>array('email1','email2')),
                  'USERS' =>array('dropTable'=>false,'columns'=>array('email1','email2'),
                        'indices'=>array('USER_NAME_IDX','idx_user_first_last',
                                         'idx_user_last_first')),
				  'OPPORTUNITIES'		  =>array('dropTable'=>false,'columns' =>array('amount_backup')),
				  'CASES'                 =>array('dropTable'=>false,'indices'=>array('idx_assigneduserid_status','idx_cases_teamid','IDX_ASS_STA_DEL')),
				  'CALLS'                 =>array('dropTable'=>false,'indices'=>array('IDX_PAR_PAR_STA_DEL','IDX_CALLS_STATUS_D')),
				  'TRACKER'               =>array('dropTable'=>false,'indices'=>array('idx_userid','idx_userid_itemid','idx_tracker_action','idx_trckr_mod_uid_dtmod_item')),
				  'EMAILS_ACCOUNTS'  	  =>array('dropTable'=>true),
				  'EMAILS_BUGS'      	  =>array('dropTable'=>true),
				  'EMAILS_CASES'     	  =>array('dropTable'=>true),
				  'EMAILS_CONTACTS'  	  =>array('dropTable'=>true),
				  'EMAILS_LEADS'     	  =>array('dropTable'=>true),
				  'EMAILS_OPPORTUNITIES'  =>array('dropTable'=>true),
				  'EMAILS_PROJECTS'       =>array('dropTable'=>true),
				  'EMAILS_PROJECT_TASKS'  =>array('dropTable'=>true),
				  'EMAILS_PROSPECTS'      =>array('dropTable'=>true),
				  'EMAILS_QUOTES'         =>array('dropTable'=>true),
				  'EMAILS_TASKS'          =>array('dropTable'=>true),
				  'EMAILS_USERS'          =>array('dropTable'=>true),
				  'PROJECT_RELATION'      =>array('dropTable'=>true)
			);

    $dbType = $sugar_config['dbconfig']['db_type'];
    $returnAllQueries = '';
	foreach($dropSchema as $table => $tableArray) {
		if($dbType == 'mysql'){
			if(isset($tableArray['dropTable']) && $tableArray['dropTable']){
			  $qT ="DROP TABLE {$table}";
			  if($return_query){
			  	$returnAllQueries .=$qT.";";
			  }
			  else{
			  	$r = $email->db->query($qT, false);
			  }
			  echo 'Dropped Table '.$qT.'</br>';
			}
			else{
			  if(isset($tableArray['columns']) && $tableArray['columns'] != null){
			  	foreach($tableArray['columns'] as $column){
			  	 $qC = "ALTER TABLE {$table} DROP COLUMN {$column}";
			  	 if($return_query){
			  		$returnAllQueries .=$qC.";";
			  	 }
			  	 else{
			  	    $r = $email->db->query($qC, false);
			  	}
			   }
			  	//take the last column out
			  	//$qC = substr($qC, 0, strlen($qC)-1);
			  	//$r = $email->db->query($qC, false,'',true);
			  }
			  if(isset($tableArray['indices']) && $tableArray['indices'] != null){
                 foreach($tableArray['indices'] as $index){
			  	  $qI ="ALTER TABLE {$table} DROP INDEX {$index}";
                  if($return_query){
			  		$returnAllQueries .=$qI.";";
			  	  }
			  	  else{
                  $r = $email->db->query($qI, false);
			  	 }
                }
			  }
			}
	    }
	  	if($dbType == 'mssql'){
			if(isset($tableArray['dropTable']) && $tableArray['dropTable']){
			  //$qT= "IF EXISTS(SELECT 1 FROM sys.objects WHERE OBJECT_ID = OBJECT_ID(N'{$table}') AND type = (N'U')) DROP TABLE {$table}";
			  $qT ="DROP TABLE {$table}";
			  if($return_query){
			  	 $returnAllQueries .=$qT.";";
			   }
			  else{
			  	 $r = $email->db->query($qT, false);
			  }
			}
			else{
			  if(isset($tableArray['columns']) && $tableArray['columns'] != null){
			  	//$qC = "ALTER TABLE {$table}"
			  	foreach($tableArray['columns'] as $column){
			  	 $qC = "ALTER TABLE {$table} DROP COLUMN {$column}";
			  	 if($return_query){
			  		$returnAllQueries .=$qC.";";
			  	 }
			  	 else{
			  	 	$r = $email->db->query($qC, false);
			  	 }
			  	}
			  	//take the last column out
			  	//$r = $email->db->query($qC, false);
			  }
			  if(isset($tableArray['indices']) && $tableArray['indices'] != null){
	            foreach($tableArray['indices'] as $index){
			  	  $qI ="DROP INDEX {$index} ON {$table}";
                  if($return_query){
			  		$returnAllQueries .=$qI.";";
			  	 }
			  	 else{
                    $r = $email->db->query($qI, false);
			  	 }
	            }
			  }
			  if(isset($tableArray['constraints']) && $tableArray['constraints'] != null){
			  	foreach($tableArray['constraints'] as $constraint){
			  	$qConst ="ALTER TABLE {$table} DROP CONSTRAINT {$constraint}";
			  	if($return_query){
			  		$returnAllQueries .=$qConst.";";
			  	 }
			  	 else{
			  		$r = $email->db->query($qConst, false);
			  	 }
			  }
			}
		  }
	    }
	   	if($dbType == 'oci8'){
			if(isset($tableArray['dropTable']) && $tableArray['dropTable']){
			  $qT ="DROP TABLE {$table} CASCADE CONSTRAINTS";
			  if($return_query){
			  	   $returnAllQueries .=$qT.";";
			  	}
			  	 else{
			  	   $r = $email->db->query($qT, false);
			  	}
			  echo 'Dropped Table '.$qT.'</br>';
			}
			else{
			  if(isset($tableArray['columns']) && $tableArray['columns'] != null){
			  	foreach($tableArray['columns'] as $column){
			  	 $qC = "ALTER TABLE {$table} DROP COLUMN {$column}";
			  	 if($return_query){
			  		$returnAllQueries .=$qC.";";
			  	 }
			  	 else{
			  	 	$r = $email->db->query($qC, false);
			  	 }
			  	}
			  }
			  if(isset($tableArray['indices']) && $tableArray['indices'] != null){
                 foreach($tableArray['indices'] as $index){
			  	  $qI ="DROP INDEX {$index}";
                 if($return_query){
			  		$returnAllQueries .=$qI.";";
			  	 }
			  	 else{
                  $r = $email->db->query($qI, false);
			  	 }
                }
			  }
			  if(isset($tableArray['constraints']) && $tableArray['constraints'] != null){
				foreach($tableArray['constraints'] as $constraint){
				  	$qConst .="ALTER TABLE {$table} DROP CONSTRAINT {$constraint}";
			  		if($return_query){
			  			$returnAllQueries .=$qConst.";";
			  	    }
			  	 else{
			  		 	$r = $email->db->query($qConst, false);
			  	   }
			     }
			  }
			}
	    }
	}

  if($return_query){
  	return $returnAllQueries;
  }
}
?>
                       'opportunities' =>'deleted',
                       'cases' => 'deleted',
                       'calls' => 'deleted',
                       'meetings' => 'deleted',
                       'bugs'  =>'deleted',
                       'campaigns'=>'deleted',
                       'prospects'=>array('do_not_call','invalid_email','email_opt_out'),
                       'fields_meta_data'=>'mass_update',
                       'project_task'=>array('order_number','utilization'));

    $q='';
    foreach($tabAndCols as $tab=>$col){
    	if(is_array($col)){
			foreach($col as $c){
				$res = query_Constraints($tab,$c);
				while($consts = $email->db->fetchByAssoc($res)){
					   $q .=' ALTER TABLE '.$tab.' DROP CONSTRAINT '.$consts['CONSTRAINT_NAME'].';';
			   }
			}
	    }
	    else{
			 //echo 'comes ere';
			 $res = query_Constraints($tab,$col);
			 while($consts = $email->db->fetchByAssoc($res)){
				  $q .=' ALTER TABLE '.$tab.' DROP CONSTRAINT '.$consts['CONSTRAINT_NAME'].';';
			 }
	     }
     }
    //echo 'proces '.$q;
    if($return_query){
    	return $q;
    }
    if($q != null){
	  $email->db->query($q,true);
	}
	return;
}

function query_Constraints($table_name,$column_name){
$email = new Email();
$q="with constraint_depends
	as
	(
		select c.TABLE_SCHEMA, c.TABLE_NAME, c.COLUMN_NAME, c.CONSTRAINT_NAME
		  from INFORMATION_SCHEMA.CONSTRAINT_COLUMN_USAGE as c
		 union all
		select s.name, o.name, c.name, d.name
		  from sys.default_constraints as d
		  join sys.objects as o
			on o.object_id = d.parent_object_id
		  join sys.columns as c
			on c.object_id = o.object_id and c.column_id = d.parent_column_id
		  join sys.schemas as s
			on s.schema_id = o.schema_id
   )
	select CONSTRAINT_NAME
	from constraint_depends
	where TABLE_NAME = '$table_name' and COLUMN_NAME = '$column_name'";

 //echo $q;
 return $email->db->query($q,true);
}
?>
