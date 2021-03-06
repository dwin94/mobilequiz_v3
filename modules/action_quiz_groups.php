<?php

function addAssignation()
{
	global $dbh;
	
	$response_array["status"] = "OK";
	
	
	if(!$_SESSION['role']['creator'])
	{
		$response_array["status"] = "error";
		$response_array["text"] = "You are not creator.";
	}
	
	$stmt = $dbh->prepare("select owner_id from questionnaire where id = :id");
	$stmt->bindParam(":id", $_POST["questionnaireId"]);
	$stmt->execute();
	if($stmt->rowCount() != 1)
	{
		$response_array["status"] = "error";
		$response_array["text"] = "No quiz found.";
	}
	$fetchOwner = $stmt->fetch(PDO::FETCH_ASSOC);
	
	if($_SESSION["id"] != $fetchOwner["owner_id"] && $_SESSION['role']['admin'] != 1 && !amIAssignedToThisQuiz($dbh, $_POST["questionnaireId"]))
	{
		$response_array["status"] = "error";
		$response_array["text"] = "You are not allowed to update this quiz.";
	}
	
	if(!isset($_POST["questionnaireId"]) || !isset($_POST["userEmail"]))
	{
		$response_array["status"] = "error";
		$response_array["text"] = "Not all parameters received.";
	}
	
	if($response_array["status"] == "error")
	{
		echo json_encode($response_array);
		exit;
	}

	
	$stmt = $dbh->prepare("select id from user where email = :email");
	$stmt->bindParam(":email", $_POST["userEmail"]);
	$stmt->execute();
	$userId = $stmt->fetch(PDO::FETCH_ASSOC);

	$stmt = $dbh->prepare("insert into qunaire_assigned_to values (:questionnaireId, :user_id)");
	$stmt->bindParam(":questionnaireId", $_POST["questionnaireId"]);
	$stmt->bindParam(":user_id", $userId["id"]);
	if($stmt->execute())
	{
		$response_array["userId"] = $userId["id"];
		
	} else {
		$response_array["status"] = ["error"];
		$response_array["text"] = ["Couldn't update database."];
	}
	
	echo json_encode($response_array);
	exit;
}


function deleteAssignation()
{
	global $dbh;
	
	if($_SESSION['role']['creator'])
	{
		$stmt = $dbh->prepare("select owner_id from questionnaire where id = :id");
		$stmt->bindParam(":id", $_GET["questionnaireId"]);
		$stmt->execute();
		$fetchOwner = $stmt->fetch(PDO::FETCH_ASSOC);
	
		if($_SESSION["id"] == $fetchOwner["owner_id"] || $_SESSION['role']['admin'] == 1)
		{
			$stmt = $dbh->prepare("delete from qunaire_assigned_to where user_id = :uId and questionnaire_id = :qId");
			$stmt->bindParam(":qId", $_GET["questionnaireId"]);
			$stmt->bindParam(":uId", $_GET["userId"]);
			if($stmt->execute())
			{
				echo "ok1";
			} else {echo "failed";}
		} else {echo "failed";}
	} else {echo "failed";}
}


function addGroup()
{
	global $dbh;
	
	if($_SESSION['role']['creator'])
	{
		do{
			$randomKey = substr(md5(uniqid(rand(), true)), 1, 6);
			$stmt = $dbh->prepare("select token from `group` where token = :token");
			$stmt->bindParam(":token", $randomkey);
			$stmt->execute();
		} while($stmt->rowCount() > 0);
			
		$stmt = $dbh->prepare("insert into `group` (name, owner_id, token) values (:groupName, :ownerId, :token)");
		$stmt->bindParam(":groupName", $_GET["groupName"]);
		$stmt->bindParam(":ownerId", $_SESSION["id"]);
		$stmt->bindParam(":token", $randomKey);
		if($stmt->execute())
		{
			addEvent($dbh, "addGroup", $_SESSION["id"] . " added the group " . $_GET["groupName"]);
			echo "ok";
		}
		else
			echo $randomKey;
	} else {echo "failed";}
}


function deleteGroup()
{
	global $dbh;
	
	if($_SESSION['role']['creator'])
	{
		$stmt = $dbh->prepare("select owner_id, name from `group` where id = :id");
		$stmt->bindParam(":id", $_GET["groupId"]);
		$stmt->execute();
		$fetchOwner = $stmt->fetch(PDO::FETCH_ASSOC);
			
		//if owner_id == NULL -> means the creator of this group grant access to all manager to manage this group
		//in the future it can changed to assign additional owners
		if($_SESSION["id"] == $fetchOwner["owner_id"] || $_SESSION['role']['admin'] == 1 || $fetchOwner["owner_id"] == NULL)
		{
			$stmt = $dbh->prepare("update user set group_id = :newGroupId where group_id = :oldGroupId");
			$stmt->bindValue(":newGroupId", NULL);
			$stmt->bindParam(":oldGroupId", $_GET["groupId"]);
			$executeUpdateUser = $stmt->execute();
	
			$stmt = $dbh->prepare("delete from `group` where id = :id");
			$stmt->bindParam(":id", $_GET["groupId"]);
			$executeDelGroup = $stmt->execute();
	
			if($executeUpdateUser && $executeDelGroup)
			{
				addEvent($dbh, "delGroup", $_SESSION["id"] . " deleted the group " . $fetchOwner["name"]);
				echo "ok";
			}
			else
				echo "failed";
		} else {echo "failed";}
	} else {echo "failed";}
}


function addUserToGroup()
{
	global $dbh;
	
	if($_SESSION['role']['creator'])
	{
		$stmt = $dbh->prepare("select owner_id from `group` where id = :id");
		$stmt->bindParam(":id", $_GET["groupId"]);
		$stmt->execute();
		$fetchOwner = $stmt->fetch(PDO::FETCH_ASSOC);
			
		//if owner_id == NULL -> means the creator of this group grant access to all manager to manage this group
		//in the future it can changed to assign additional owners
		if($_SESSION["id"] == $fetchOwner["owner_id"] || $_SESSION['role']['admin'] == 1 || $fetchOwner["owner_id"] == NULL)
		{
			$stmt = $dbh->prepare("update user set group_id = :newGroupId where email = :uEmail");
			$stmt->bindValue(":newGroupId", $_GET["groupId"]);
			$stmt->bindParam(":uEmail", $_GET["userEmail"]);
			if($stmt->execute())
				echo json_encode(["ok", $stmt->rowCount()]);
				else
					echo  json_encode(["failed"]);
		}
	} else {echo json_encode(["failed"]);}
}


function deleteUserFromGroup()
{
	global $dbh;
	
	if($_SESSION['role']['creator'])
	{
		$stmt = $dbh->prepare("select owner_id from `group` where id = :id");
		$stmt->bindParam(":id", $_GET["groupId"]);
		$stmt->execute();
		$fetchOwner = $stmt->fetch(PDO::FETCH_ASSOC);
	
		//if owner_id == NULL -> means the creator of this group grant access to all manager to manage this group
		//in the future it can changed to assign additional owners
		if($_SESSION["id"] == $fetchOwner["owner_id"] || $_SESSION['role']['admin'] == 1 || $fetchOwner["owner_id"] == NULL)
		{
			$stmt = $dbh->prepare("update user set group_id = :newGroupId where id = :uId");
			$stmt->bindValue(":newGroupId", NULL);
			$stmt->bindParam(":uId", $_GET["userId"]);
			if($stmt->execute())
				echo "ok";
				else
					echo "failed";
		}
	} else {echo "failed";}
}

function changeAssignedGroups()
{
	global $dbh;
	
	if($_SESSION['role']['creator'])
	{
		$stmt = $dbh->prepare("select owner_id from questionnaire where id = :id");
		$stmt->bindParam(":id", $_GET["questionnaireId"]);
		$stmt->execute();
		$fetchOwner = $stmt->fetch(PDO::FETCH_ASSOC);
	
		if($_SESSION["id"] == $fetchOwner["owner_id"] || $_SESSION['role']['admin'] || amIAssignedToThisQuiz($dbh, $_GET["questionnaireId"]))
		{
			$groups=json_decode($_GET["groups"]);
	
			$stmt = $dbh->prepare("delete from assign_group_qunaire where questionnaire_id = :qId");
			$stmt->bindParam(":qId", $_GET["questionnaireId"]);
			$stmt->execute();
	
			for ($i = 0; $i < count($groups); $i++) {
				$stmt = $dbh->prepare("insert into assign_group_qunaire (group_id, questionnaire_id) values (:gId, :qId)");
				$stmt->bindParam(":gId", $groups[$i]);
				$stmt->bindParam(":qId", $_GET["questionnaireId"]);
				if(!$stmt->execute())
					echo "fail";
			}
			echo "ok";
		}else {echo "fail";}
	}else {echo "fail";}
}

include_once 'authorizationCheck_participation.php';

function revealUserName()
{

	global $dbh;
	
	if(amIQuizCreator($_GET["questionnaireId"]) || $_SESSION['role']['admin'] || amIAssignedToThisQuiz($dbh, $_GET["questionnaireId"]))
	{
		$stmt = $dbh->prepare("select firstname, lastname from user_data where user_id = :uId");
		$stmt->bindParam(":uId", $_GET["userId"]);
		$stmt->execute();
		$fetchUserName = $stmt->fetch(PDO::FETCH_ASSOC);

		echo json_encode(["ok", $fetchUserName["lastname"] . " " . $fetchUserName["firstname"]]);
	} else {echo json_encode(["failed"]);}
}

?>