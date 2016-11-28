<?php
	session_start();
	
	include_once 'action_topic.php';
	include_once 'action_question.php';
	include_once 'action_quiz.php';
	include_once 'action_quiz_groups.php';
	include_once 'action_poll.php';
	include_once 'action_user.php';
	
	include_once '../config/config.php';
	include_once "../modules/extraFunctions.php";

	$action = -1;
	if(isset($_GET["action"]))
	{
		$action = $_GET["action"];
	}
	
	switch($action) {
		case "insertTopic":
			insertTopic();
			break;
		case "delTopic":
			deleteTopic();
			break;
		case "insertQuestion":
			insertQuestion();
			break;
		case "delQuestion":
			deleteQuestion();
			break;
		case "delPicture":
			deletePicture();
			break;
		case "insertQuiz":
			insertQuiz();
			break;
		case "addQuestion":
			addQuestion();
			break;
		case "delQuestionFromQuiz":
			deleteQuestionFromQuiz();
			break;
		case "delQuiz":
			deleteQuiz();
			break;
		case "addAssignation":
			addAssignation();
			break;
		case "delAssignation":
			deleteAssignation();
			break;
		case "moveQuestion":
			moveQuestion();
			break;
		case "delGroup":
			deleteGroup();
			break;
		case "addGroup":
			addGroup();
			break;
		case "delUserFromGroup":
			deleteUserFromGroup();
			break;
		case "addUserToGroup":
			addUserToGroup();
			break;
		case "createPoll":
			createPoll();
			break;
		case "getPollVotes":
			getPollVotes();
			break;
		case "switchPollState":
			switchPollState();
			break;
		case "sendVote":
			sendVote();
			break;
		case "changeActive":
			changeActiveStateUser();
			break;
		case "getCorrectAnswers":
			getCorrectPollAnswers();
			break;
		case "changeAssignedGroups":
			changeAssignedGroups();
			break;
		case "revealUserName":
			revealUserName();
			break;
		case "queryAnswers":
			queryAnswers();
			break;
		default:
			header("Location: ?p=quiz&code=-1&info=ppp");
	}
	
?>