<?php
session_start();

include_once '../config/config.php';
include_once 'mail.php';

$action = -1;
$fromSite = -1;
if(isset($_GET["action"]))
{
	$action = $_GET["action"];
}
if(isset($_GET["fromsite"]))
{
	$fromSite = $_GET["fromsite"];
}
//----------
if($_SESSION["role"]["user"] != 1)
{
	header("Location: index.php?p=home&code=-20");
	exit;
}

if($action == "startQuiz")
{
	
	$id = 0;
	if(isset($_GET["execId"]))
	{
		$id = $_GET["execId"];
	} else {
		header("Location: index.php?p=quiz&code=-2");
		exit;
	}
	
	$stmt = $dbh->prepare("select amount_participations, public, starttime, endtime, noParticipationPeriod, questionnaire.id as qId from questionnaire 
			inner join qunaire_exec on qunaire_exec.questionnaire_id = questionnaire.id inner join execution on qunaire_exec.execution_id = execution.id  where execution.id = :id");
	$stmt->bindParam(":id", $id);
	$stmt->execute();
	$fetchQuiz = $stmt->fetch(PDO::FETCH_ASSOC);
	if($stmt->rowCount() <= 0)
	{
		header("Location: index.php?p=quiz&code=-15");
		exit;
	}
	
	include_once 'modules/authorizationCheck_participation.php';
	checkAuthorization($fetchQuiz["qId"], $fetchQuiz, false);
	
	//max participations
	$stmt = $dbh->prepare("select user_exec_session.id from user_exec_session inner join qunaire_exec on user_exec_session.execution_id = qunaire_exec.execution_id 
							where qunaire_exec.execution_id = :id and user_exec_session.user_id = :user_id");
	$stmt->bindParam(":id", $id);
	$stmt->bindParam(":user_id", $_SESSION["id"]);
	$stmt->execute();
	$participations = $stmt->rowCount();
	if($fetchQuiz["amount_participations"] != 0 && $fetchQuiz["amount_participations"] <= $participations && $_SESSION["role"]["admin"] != 1)
	{
		header("Location: index.php?p=quiz&code=-35");
		exit;
	}
	
	$stmt = $dbh->prepare("insert into user_exec_session (user_id, execution_id, starttime) values (:user_id, :execution_id, :starttime)");
	$stmt->bindParam(":user_id", $_SESSION["id"]);
	$stmt->bindParam(":execution_id", $id);
	$stmt->bindParam(":starttime", time());
	if($stmt->execute())
	{
		$_SESSION["quizSession"] = $id;
		$_SESSION["idSession"] = $dbh->lastInsertId();
		$_SESSION["questionNumber"] = 0;
		$_SESSION["additionalTime"] = 0;
		header("Location: ?p=participate");
		exit;
	} else {
		header("Location: index.php?p=quiz&code=-16");
		exit;
	}
	
} else if($action == "endQuiz")
{
	$stmt = $dbh->prepare("update user_exec_session set endtime = :endtime, end_state = :end_state where id = :idSession"); //incomplete statement (points, finished in time)
	$stmt->bindParam(":idSession", $_SESSION["idSession"]);
	$stmt->bindParam(":endtime", time());
	$code = 0;
	
	$interruptedExecution = false;
	switch ($_GET["state"])
	{
		case 'correct':
			$code = 1;
			break;
		case 'timeExceeded':
			$interruptedExecution = true;
			$code = 2;
			break;
		case 'abort':
			$interruptedExecution = true;
			$code = 3;
			break;
		default:
			$interruptedExecution = true;
			$code = 3;
			break;
	}
	$stmt->bindParam(":end_state", $code);
	
	if(!$stmt->execute()){
		header("Location: index.php?p=quiz&code=-37");
		exit;
	}
	
	if($interruptedExecution)
	{
		$stmt = $dbh->prepare("select question_id from an_qu_user where session_id = :idSession group by question_id");
		$stmt->bindParam(":idSession", $_SESSION["idSession"]);
		$stmt->execute();
		$answeredQuestionsId = $stmt->fetchAll(PDO::FETCH_ASSOC);
		
		$stmt = $dbh->prepare("select question_id from qunaire_qu inner join qunaire_exec on qunaire_qu.questionnaire_id = qunaire_exec.questionnaire_id where qunaire_exec.execution_id = :execId");
		$stmt->bindParam(":execId", $_SESSION["quizSession"]);
		$stmt->execute();
		$allQuestionsId = $stmt->fetchAll(PDO::FETCH_ASSOC);
		
		$toBeStoredQuestionIds = array();
		
		for($j=0; $j < count($allQuestionsId); $j++){
			if(!in_array($allQuestionsId[$j], $answeredQuestionsId)){
				array_push($toBeStoredQuestionIds, $allQuestionsId[$j]);
			}
		}
		
		$answerIds = array();
		foreach($toBeStoredQuestionIds as $value) {
			$testId = $value["question_id"];
			$questionNumber = ++$_SESSION["questionNumber"];
			saveQuestion($testId, $questionNumber, "noAnswer");
		}
		
	}
	
	if($stmt->execute())
	{
		$execId = $_SESSION["quizSession"];
		$_SESSION["quizSession"] = -1;
		$_SESSION["idSession"] = -1;
		$_SESSION["questionNumber"] = -1;
		$_SESSION["unansweredNumber"] = -1;
		$_SESSION["next_button_time"] = -1;
		$_SESSION["additionalTime"] = -1;
		header("Location: ?p=participationOutro&execId=" . $execId);
		exit;
	} else {
		header("Location: index.php?p=quiz&code=-37");
		exit;
	}
} else if(isset($_POST["action"]) && $_POST["action"] == 'saveAndNextQuestion')
{
	if(isset($_POST["questionId"]) && (isset($_POST["prevQuestion"]) || isset($_POST["nextQuestion"]))) 
	{
		
		saveQuestion($_POST["questionId"], $_SESSION["questionNumber"], $_POST["answer"]);
		
		if(isset($_POST["prevQuestion"]))
		{
			$_SESSION["questionNumber"]--;
			header("Location: ?p=participate");
			exit;
		} else if(isset($_POST["nextQuestion"]))
		{
			if(isset($_POST["unanswered"]) && $_POST["unanswered"] == "1")
			{
				header("Location: ?p=participate&info=unanswered");
				exit;
			} else {
				$_SESSION["questionNumber"]++;
				$_SESSION["next_button_time"] = $_POST["startTimeNextButton"];
				header("Location: ?p=participate");
				exit;
			}
		}
	} else if(isset($_POST["prevQuestion"]))
	{
		$_SESSION["questionNumber"]--;
		header("Location: ?p=participate");
		exit;
	} else {
		header("Location: index.php?p=quiz&code=-24");
		exit;
	}
} else if(isset($_GET["action"]) && isset($_GET["action"]) == 'insertNextButtonWaitTime')
{ 
	if($_SESSION["questionNumber"] > 0 && $_SESSION["next_button_time"] != -1)
	{
		$stmt = $dbh->prepare("select next_button_time from an_qu_user where session_id = :session_id and question_order = :qO");
		$stmt->bindParam(":session_id", $_SESSION["idSession"]);
		if(!isset($_GET["customQOrder"]))
			$qO = $_SESSION["questionNumber"]-1;
		else 
			$qO = $_GET["customQOrder"]-1;
		$stmt->bindParam(":qO", $qO);
		$stmt->execute();
		$fetchTimeNeededRowCount = $stmt->rowCount();
		$fetchTimeNeeded = $stmt->fetch(PDO::FETCH_ASSOC);
		
		$stmt = $dbh->prepare("update an_qu_user set next_button_time = :next_button_time where session_id = :session_id and question_order = :qO");
		$stmt->bindParam(":session_id", $_SESSION["idSession"]);
		$stmt->bindParam(":qO", $qO);
		
		if($fetchTimeNeeded["next_button_time"] == 0)
		{
			//$nextButtonTimeNeeded = round(microtime(true) * 1000) - $_SESSION["next_button_time"];
			$nextButtonTimeNeeded = $_GET["time"] - $_SESSION["next_button_time"];
			$_SESSION["additionalTime"] += $nextButtonTimeNeeded;
		}
		else
		{
			$nextButtonTimeNeeded = $fetchTimeNeeded["next_button_time"];
		}
		$stmt->bindParam(":next_button_time", $nextButtonTimeNeeded);
		if(!$stmt->execute())
		{
			echo json_encode(["failed", $nextButtonTimeNeeded]);
			exit;
		} else {
			echo json_encode(["ok", $nextButtonTimeNeeded, $_GET["time"], $_SESSION["next_button_time"], $_SESSION["idSession"], $_GET["questionId"], $qO]);
			exit;
		}
	}
} else if(isset($_POST["action"]) && $_POST["action"] == 'participantQuestion') {
	
	if(!isset($_COOKIE["sendParticipantQuestion"]))
	{
		$stmt = $dbh->prepare("select email, firstname, lastname from user inner join questionnaire on user.id = questionnaire.owner_id inner join user_data on user.id = user_data.user_id 
							inner join qunaire_exec on qunaire_exec.questionnaire_id = questionnaire.id where qunaire_exec.execution_id = :execId");
		$stmt->bindParam(":execId", $_SESSION["quizSession"]);
		$stmt->execute();
		$fetchAnswers = $stmt->fetchAll(PDO::FETCH_ASSOC);
		
		$creatorName = $fetchAnswers[0]["firstname"] . " " . $fetchAnswers[0]["lastname"];
		$creatorMail = $fetchAnswers[0]["email"];
		
		
		$stmt = $dbh->prepare("select email, firstname, lastname from user inner join user_data on user.id = user_data.user_id where user.id = :userId");
		$stmt->bindParam(":userId", $_SESSION["id"]);
		$stmt->execute();
		$fetchAnswers = $stmt->fetchAll(PDO::FETCH_ASSOC);
		
		$participantName = htmlspecialchars($fetchAnswers[0]["firstname"] . " " . $fetchAnswers[0]["lastname"]);
		$participantMail = $fetchAnswers[0]["email"];
		
		$participantText = htmlspecialchars($_POST["questionText"]);
		$currentQuestionText = htmlspecialchars($_SESSION["choosedQuestion"]["text"]);
		
		
		$stmt = $dbh->prepare("select answer.text, question.type_id from answer inner join answer_question on answer.id = answer_question.answer_id inner join question on answer_question.question_id = question.id where question.id = :questionId");
		$stmt->bindParam(":questionId", intval($_SESSION["choosedQuestion"]["id"]));
		$stmt->execute();
		$fetchQuizAnswer = $stmt->fetchAll(PDO::FETCH_ASSOC);
		$answerText = "";
		for($i = 0; $i < count($fetchQuizAnswer); $i++)
		{
			$answerText .= "<li>" . htmlspecialchars($fetchQuizAnswer[$i]["text"]) . "</li>";
		}
		
		$questionType = "Singlechoice";
		if($fetchQuizAnswer[0]["type_id"] == "2")
		{
			$questionType = "Multiplechoice";
		}
		
		$creatorEmailText = $lang["creatorEmailText"];
		$creatorEmailText = str_replace("[participantMail]", $participantMail, $creatorEmailText);
		$creatorEmailText = str_replace("[participantName]", $participantName, $creatorEmailText);
		$creatorEmailText = str_replace("[currentQuestionText]", $currentQuestionText, $creatorEmailText);
		$creatorEmailText = str_replace("[questionType]", $questionType, $creatorEmailText);
		$creatorEmailText = str_replace("[answers]", $answerText, $creatorEmailText);
		$creatorEmailText = str_replace("[participantText]", $participantText, $creatorEmailText);
				
		$participantEmailText = $lang["participantEmailText"];
		$participantEmailText = str_replace("[creatorMail]", $creatorMail, $participantEmailText);
		$participantEmailText = str_replace("[creatorName]", $creatorName, $participantEmailText);
		$participantEmailText = str_replace("[currentQuestionText]", $currentQuestionText, $participantEmailText);
		$participantEmailText = str_replace("[questionType]", $questionType, $participantEmailText);
		$participantEmailText = str_replace("[answers]", $answerText, $participantEmailText);
		$participantEmailText = str_replace("[participantText]", $participantText, $participantEmailText);
		
		$mailBlockSeconds = 15; //Seconds how long mail will be blocked
		
		if(sendMail($creatorMail, $lang["creatorEmailSubject"], $creatorEmailText) &&
		sendMail($participantMail, $lang["participantEmailSubject"], $participantEmailText))
		{
			setcookie("sendParticipantQuestion", time() . "_" . $mailBlockSeconds, time()+$mailBlockSeconds);
			$response_array["status"] = "success";
			$response_array["text"] = $lang["SucessSentQuestion"];
		} else {
			$response_array["status"] = "error";
			$response_array["text"] = $lang["FailedToSendQuestion"];
		}
		
	} else 
	{
		$response_array["status"] = "error";
		$response_array["text"] = $lang["WaitToSendMail"];
	}
	
	header('Content-type: application/json');
	echo json_encode($response_array);
	
	exit;
	
	
} else {
	header("Location: index.php?p=home&code=-20");
	exit;
}

function saveQuestion($questionId, $questionOrder, $answer) {
	global $dbh;
	
	$stmt = $dbh->prepare("select answer.id, question.type_id from answer inner join answer_question on answer_question.answer_id = answer.id inner join question on question.id = answer_question.question_id where answer_question.question_id = :question_id");
	$stmt->bindParam(":question_id", $questionId);
	$stmt->execute();
	$fetchAnswers = $stmt->fetchAll(PDO::FETCH_ASSOC);
	
	for($i = 0; $i < count($fetchAnswers); $i++)
	{
		$stmt = $dbh->prepare("select time_needed from an_qu_user where session_id = :session_id and answer_id = :answer_id and question_id = :question_id");
		$stmt->bindParam(":session_id", $_SESSION["idSession"]);
		$stmt->bindParam(":answer_id", $fetchAnswers[$i]["id"]);
		$stmt->bindParam(":question_id", $questionId);
		$stmt->execute();
		$fetchTimeNeededRowCount = $stmt->rowCount();
		$fetchTimeNeeded = $stmt->fetch(PDO::FETCH_ASSOC);
			
		$stmt = $dbh->prepare("replace into an_qu_user (session_id, answer_id, question_id, selected, time_needed, question_order)
					values (:session_id, :answer_id, :question_id, :selected, :time_needed, :question_order)");
		$stmt->bindParam(":session_id", $_SESSION["idSession"]);
		$stmt->bindParam(":answer_id", $fetchAnswers[$i]["id"]);
		$stmt->bindParam(":question_id", $questionId);
		$stmt->bindParam(":question_order", $questionOrder);
		
		if($fetchAnswers[$i]["type_id"] == 1) //question_type == 1 Singlechoice
		{
			if($answer != "noAnswer")
			{
				if($answer == $fetchAnswers[$i]["id"])
				{ $isSelected = 1; } else { $isSelected = 0; }
				//$isSelected = $_POST["answer"] == $fetchAnswers[$i]["id"];
			} else {
				$isSelected = NULL;
			}
		} else if($fetchAnswers[$i]["type_id"] == 2) //question_type == 2 Multiplechoice
		{
			if(isset($_POST["answer_" . $fetchAnswers[$i]["id"]]))
				$isSelected = $_POST["answer_" . $fetchAnswers[$i]["id"]];
				else
					$isSelected = 0;
		}
			
		$stmt->bindParam(":selected", $isSelected);
		//$stmt->bindValue(":selected", NULL);
		if($fetchTimeNeededRowCount == 0)
		{
			if(isset($_POST["generationTime"])) {
				$timeNeeded = time() - $_POST["generationTime"];
			} else {
				$timeNeeded = 0;
			}
			
		}
		else
		{
			$timeNeeded = $fetchTimeNeeded["time_needed"];
		}
		$stmt->bindParam(":time_needed", $timeNeeded);
		//$stmt->bindValue(":time_needed", 0);
		if(!$stmt->execute())
		{
			header("Location: index.php?p=quiz&code=-23&info=participationTime" . "selected:" . $isSelected . "timeNeeded: " . $timeNeeded . "Error: " . $stmt->errorInfo()[0] . " POSTANSWER: " . $answer . " FETCHANSWER: " . $fetchAnswers[$i]["id"] . " equal: " . ($answer == $fetchAnswers[$i]["id"]));
			exit;
		}
	}
}

?>
