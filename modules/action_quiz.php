<?php

include_once 'action_quiz_question_common.php';

function updateQuiz() {
	global $dbh;
	$response_array["status"] = "OK";
	
	//check correct owner
	if($_POST["mode"] == 'edit')
	{
		//fetch owner of this quiz
		$stmt = $dbh->prepare("select owner_id from questionnaire where id = :q_id");
		$stmt->bindParam(":q_id", $_POST["quizId"]);
		$stmt->execute();
		$fetchQuizOwner = $stmt->fetch(PDO::FETCH_ASSOC);
	
		//return if it is not the owner of this quiz
		if($fetchQuizOwner["owner_id"] != $_SESSION["id"] && $_SESSION["role"]["admin"] != 1 && !amIAssignedToThisQuiz($dbh, $_POST["quizId"]))
		{
			$response_array["status"] = "error";
			$response_array["text"] = $lang["quiz-authorization-error"];
		}
	}
		
	$field = $_GET["field"];
	if(!isset($_POST["quizId"]) || !isset($field) || !isset($_POST[$field]))
	{
		$response_array["status"] = "error";
		$response_array["text"] = $lang["parameterError"];
	}
	
	if($response_array["status"] == "error")
	{
		echo json_encode($response_array);
		exit;
	}
	
	switch($field)
	{
		case "quizText":
			$response_array = updateQuizText($_POST["quizText"], $_POST["maxChar"], $_POST["quizId"], $dbh);
			break;
		case "description":
			$response_array = updateQuizDescription($_POST["description"], $_POST["maxChar"], $_POST["quizId"], $dbh);
			break;
		case "language":
			$response_array = updateLanguage($_POST["language"], "questionnaire", $_POST["quizId"], $dbh);
			break;
		case "topic":
			$response_array = updateTopic($_POST["topic"], "questionnaire" , $_POST["quizId"], $dbh);
			break;
	}
	
	if($response_array["status"] == "OK")
	{
		$stmt = $dbh->prepare("update questionnaire set last_modified = ".time()." where id = :quiz_id");
		$stmt->bindParam(":quiz_id", $_POST["quizId"]);
		if(! $stmt->execute())
		{
			$response_array["status"] = "error";
			$response_array["text"] = $lang["DB-Update-Error"];
		}
	}
	
	echo json_encode($response_array);
	exit;
}


function updateQuizText($quizText, $maxChar, $quizId, $dbh)
{
	$response_array["status"] = "OK";
	
	if(strlen($quizText) > $maxChar) {
		$response_array["status"] = "error";
		$response_array["text"] = $lang["inputToLong"];
		return $response_array;
	}

	$stmt = $dbh->prepare("update questionnaire set name = :text where id = :quiz_id");
	$stmt->bindParam(":text", $quizText);
	$stmt->bindParam(":quiz_id", $quizId);
	
	if(! $stmt->execute())
	{
		$response_array["status"] = "error";
		$response_array["text"] = $lang["DB-Update-Error"];
	}
	
	return $response_array;
}

function updateQuizDescription($description, $maxChar, $quizId, $dbh)
{
	$response_array["status"] = "OK";

	if(strlen($description) > $maxChar) {
		$response_array["status"] = "error";
		$response_array["text"] = $lang["inputToLong"];
		return $response_array;
	}

	$stmt = $dbh->prepare("update questionnaire set description = :text where id = :quiz_id");
	$stmt->bindParam(":text", $description);
	$stmt->bindParam(":quiz_id", $quizId);

	if(! $stmt->execute())
	{
		$response_array["status"] = "error";
		$response_array["text"] = $lang["DB-Update-Error"];
	}

	return $response_array;
}


function uploadExcel()
{
	global $dbh;
	
	$response_array["status"] = "OK";
	
	if($_POST["uploadType"] == "withImages")
	{
		$excelTemplate = array();
		$uploadedImages = array();
	
		for($i = 0; $i < count($_FILES); $i++)
		{
			$file = $_FILES["file_" . $i];
			
			if(strtolower(pathinfo($file["name"], PATHINFO_EXTENSION)) == "xlsx")
			{
				$excelTemplate["name"] = $file["name"];
				$excelTemplate["type"] = $file["type"];
				$excelTemplate["tmp_name"] = $file["tmp_name"];
				$excelTemplate["error"] = $file["error"];
				$excelTemplate["size"] = $file["size"];
			} else
			{
				$image = array();
				$image["name"] = $file["name"];
				$image["type"] = $file["type"];
				$image["tmp_name"] = $file["tmp_name"];
				$image["error"] = $file["error"];
				$image["size"] = $file["size"];
				array_push($uploadedImages, $image);
			}
		}
	
		foreach($uploadedImages as $image)
		{
			if ($image["error"] > 0) {
				$response_array["status"] = "error";
				$response_array["text"] = $lang["errorWhileUploading"];
				echo json_encode($response_array);
				exit;
			}
		}
	
		if(!isset($excelTemplate))
		{
			$response_array["status"] = "error";
			$response_array["text"] = $lang["noExcelFileError"];
			echo json_encode($response_array);
			exit;
		}
			
	} elseif (isset($_FILES["excelFile"]) && $_FILES["excelFile"]["name"] != "")
	{
		if(strtolower(pathinfo($_FILES["excelFile"]["name"], PATHINFO_EXTENSION)) == "xlsx")
		{
			$excelTemplate["name"] = $_FILES["excelFile"]["name"];
			$excelTemplate["type"] = $_FILES["excelFile"]["type"];
			$excelTemplate["tmp_name"] = $_FILES["excelFile"]["tmp_name"];
			$excelTemplate["error"] = $_FILES["excelFile"]["error"];
			$excelTemplate["size"] = $_FILES["excelFile"]["size"];
		}
	
		if(!isset($excelTemplate))
		{
			$response_array["status"] = "error";
			$response_array["text"] = $lang["noExcelFileError"];
			echo json_encode($response_array);
			exit;
		}
	}
	
	//error while uploading
	if ($excelTemplate["error"] > 0) {
		$response_array["status"] = "error";
		$response_array["text"] = $lang["errorWhileUploading"];
		echo json_encode($response_array);
		exit;
	}

	include_once 'importExcel.php';

	$excelContent = importExcel($excelTemplate);
	$questions = createQuestionArray($excelContent);

	//Excel contains no questions
	if(count($questions) == 0)
	{
		$response_array["status"] = "error";
		$response_array["text"] = $lang["noQuestionsInExcelError"];
		echo json_encode($response_array);
		exit;
	}

	$htmlQuestionCounter = 0;
	
	foreach($questions as $question)
	{
		if($question->getNumberOfCorrectAnswers() == 0)
		{
			$response_array["status"] = "error";
			$response_array["text"] = $lang["questionWithoutAnswerError"];
			echo json_encode($response_array);
			exit;
		}
	}


	$invalidQuestions = array();
	$imageCounter = 1;
	
	
	$stmt = $dbh->prepare("select count(question_id) as total from qunaire_qu where questionnaire_id = :qId");
	$stmt->bindParam(":qId", $_POST["quizId"]);
	$stmt->execute();
	$fetchTotal = $stmt->fetch(PDO::FETCH_ASSOC);
	$orderCounter = $fetchTotal["total"];

	foreach($questions as $question)
	{		
		//check if questiontext AND all answers are already there
		//if yes use this id instead of insert the same question
		$stmt = $dbh->prepare("select question.id as qId, question.text as qText from question where question.text = :text");
		$stmt->bindParam(":text", $question->getText());
		$stmt->execute();
			
		$numOfEqualQuestions = $stmt->rowCount();
		$allIn = false;
			
		if($numOfEqualQuestions > 0) //Question already exists
		{
			$stmt = $dbh->prepare("select question.id as qId, question.text as qText, answer.id as aId, answer.text as aText, is_correct from question inner join answer_question on answer_question.question_id = question.id inner join answer on answer.id = answer_question.answer_id where question.text = :text");
			$stmt->bindParam(":text", $question->getText());
			$stmt->execute();

			$allInCount = 0;
			$fetchCheckQuestion = $stmt->fetchAll(PDO::FETCH_ASSOC);

			//compare all DB-Answers to all Excel-Answers
			for($i = 0; $i < count($fetchCheckQuestion); $i++) //DB-Answers
			{
				for($j = 0; $j < $question->getNumberOfAnswers(); $j++) //Excel-Answers
				{
					$dbQuestionText = $fetchCheckQuestion[$i]["aText"];
					$excelQuestionText = $question->getAnswers()[$j]->getText();
					if($dbQuestionText == $excelQuestionText)
					{
						$allInCount++;
						$response_array["text"] = $lang["existingQuestionMessage"];
					}
				}
					
				if($allInCount == $question->getNumberOfAnswers())
				{
					$allIn = true;
				}
			}
		}
			
		if(!$allIn)
		{
			//Singlechoice with != 1 correct Answers
			if(!$question->isValid())
			{
				array_push($invalidQuestions, htmlspecialchars($question->getText()));
			}
				
			$language = "Deutsch";
			if($_SESSION["language"] == "en")
			{
				$language = "English";
			}

			$imageName = $question->getImage();
			$uploadedImagePath = null;
			if(isset($imageName))
			{
				foreach($uploadedImages as $uploadedImage)
				{
					if($uploadedImage["name"] == $imageName)
					{
						//Check if file is image
						if(!getimagesize($uploadedImage["tmp_name"]))
						{
							$response_array["status"] = "error";
							$response_array["text"] = $lang["fileNotSupportetError"];
							echo json_encode($response_array);
							exit;
						}
						$uploadedQuestionImage = $uploadedImage;
							
					}
				}
					
				if(!isset($uploadedQuestionImage))
				{
					$response_array["status"] = "error";
					$response_array["text"] = $lang["ExcelQuestionImageError"];
					echo json_encode($response_array);
					exit;
				}

				$uploadedImageFileType = strtolower(pathinfo($uploadedQuestionImage["name"], PATHINFO_EXTENSION));
				$uploadedImagePath = "uploadedImages/" . "question_" . date("d_m_y_H_i_s", time()) . "__" . $imageCounter . "__" . $_SESSION["id"] . "." . $uploadedImageFileType;
				if(!move_uploaded_file($uploadedQuestionImage["tmp_name"], $uploadedImagePath))
				{
					$response_array["status"] = "error";
					$response_array["text"] = $lang["ImageNotSavedError"];
					echo json_encode($response_array);
					exit;
				}
					
				$imageCounter++;
					
			}


			//insert Question
			$stmt = $dbh->prepare("insert into question	(text, owner_id, type_id, subject_id, language, creation_date, public, last_modified, picture_link) values (:text, :owner_id, :type_id, :subject_id, :language, ".time().", :public, ".time().", :picLink)");
			$stmt->bindParam(":text", $question->getText());
			$stmt->bindParam(":owner_id", $_SESSION["id"]);
			$stmt->bindParam(":type_id", $question->getTypeCode());
			$stmt->bindValue(":subject_id", NULL);
			$stmt->bindValue(":language", $language);
			$stmt->bindValue(":public", 0);
			$stmt->bindValue(":picLink", $uploadedImagePath);
			if(!$stmt->execute())
			{
				$response_array["status"] = "error";
				$response_array["text"] = $lang["DB-Insert-Error-question"];
				echo json_encode($response_array);
				exit;
			}

			$insertedQuestionId = $dbh->lastInsertId();
			$answerNumber = 0;
			
			//insert all Answers
			foreach($question->getAnswers() as $answer)
			{
				$stmt = $dbh->prepare("insert into answer (text) values (:text)");
				$stmt->bindParam(":text", $answer->getText());
				if(!$stmt->execute())
				{
					$response_array["status"] = "error";
					$response_array["text"] = $lang["DB-Insert-Error-answer"];
					echo json_encode($response_array);
					exit;
				}
					
				$insertedAnswerId = $dbh->lastInsertId();
					
				if($question->getTypeCode() == 1) //Singlechoice
				{
					if($answer->isCorrect())
					{
						$isCorrect = 1;
					} else {
						$isCorrect = 0;
					}
				} else { //Multiplechoice
					if($answer->isCorrect())
					{
						$isCorrect = 1;
					} else {
						$isCorrect = -1;
					}
				}
					
				$stmt = $dbh->prepare("insert into answer_question values (:answer_id, :question_id, :is_correct, :order)");
				$stmt->bindParam(":answer_id", $insertedAnswerId);
				$stmt->bindParam(":question_id", $insertedQuestionId);
				$stmt->bindParam(":is_correct", $isCorrect);
				$stmt->bindValue(":order", $answerNumber);

				if(!$stmt->execute())
				{
					$response_array["status"] = "error";
					$response_array["text"] = $lang["DB-Insert-Error-answer_question"];
					echo json_encode($response_array);
					exit;
				}
				$answerNumber++;
			}
		} else
		{
			$insertedQuestionId = $fetchCheckQuestion[0]["qId"];
		}
		
		$stmt = $dbh->prepare("select question_id from qunaire_qu where questionnaire_id = :qunaireId");
		$stmt->bindParam(":qunaireId", $_POST["quizId"]);
		$stmt->execute();
		$fetchExistingQuestions = $stmt->fetchAll(PDO::FETCH_ASSOC);
		
		$questionAlreadyAssigned = false;
		
		for($i = 0; $i < count($fetchExistingQuestions); $i++)
		{
			if($insertedQuestionId == $fetchExistingQuestions[$i]["question_id"])
			{
				$questionAlreadyAssigned = true;
			}
		}
		
		if($questionAlreadyAssigned)
		{
			$response_array["text"] = $ang["QuestionAlreadyInQuiz"];
			continue;
		}
			
		
		$stmt = $dbh->prepare("insert into qunaire_qu (questionnaire_id, question_id, `order`) values (:questionnaire_id, :question_id, :order)");
		$stmt->bindParam(":questionnaire_id", $_POST["quizId"]);
		$stmt->bindParam(":question_id", $insertedQuestionId);
		$stmt->bindParam(":order", $orderCounter);
			
		if(!$stmt->execute())
		{
			$response_array["status"] = "error";
			$response_array["text"] = $lang["DB-Insert-Error-qunaire_qu"];
			echo json_encode($response_array);
			exit;
		}
		
		$response_array["questionInfo_$htmlQuestionCounter"] = getQuestionInfos($insertedQuestionId, $_POST["quizId"]);
		$htmlQuestionCounter++;
		
		$orderCounter++;
	}
	
	$response_array["counter"] = $htmlQuestionCounter;
	echo json_encode($response_array);
	exit;
}


/**
 * Method to return the necessary information to display the uploaded answers with AJAX
 */
function getQuestionInfos($questionId, $quizId)
{
	global $dbh;
	
	$stmt = $dbh->prepare("select count(question_id) as total from qunaire_qu where questionnaire_id = :qId");
	$stmt->bindParam(":qId", $quizId);
 	$stmt->execute();
 	$fetchTotal = $stmt->fetch(PDO::FETCH_ASSOC);
 	$nextId = $fetchTotal["total"];
	
	$stmt = $dbh->prepare("select * from question where id = :qId");
	$stmt->bindParam(":qId", $questionId);
	$stmt->execute();
	$fetchQuestion = $stmt->fetch(PDO::FETCH_ASSOC);
	
	$id = $fetchQuestion["id"];
	$text = $fetchQuestion["text"];
	
	$qType = "singlechoice";
	if($fetchQuestion["type_id"] == 2)
	{
		$qType = "multiplechoice";
	}
	
	
	$stmt = $dbh->prepare("select count(answer_id) as totalAnswers from answer_question where question_id = :qId");
	$stmt->bindParam(":qId", $questionId);
	$stmt->execute();
	$fetchTotalAnswers = $stmt->fetch(PDO::FETCH_ASSOC);
	$totalAnswers = $fetchTotalAnswers["totalAnswers"];
	
	
	$stmt = $dbh->prepare("select * from an_qu_user inner join answer_question on answer_question.answer_id = an_qu_user.answer_id where an_qu_user.question_id = :qId");
	$stmt->bindParam(":qId", $questionId);
	$stmt->execute();
	$fetchAnQuUser = $stmt->fetchAll(PDO::FETCH_ASSOC);
	$correctCounter = 0;
	$totalCounter = 0;
	for($j = 0; $j < count($fetchAnQuUser); $j++)
	{
		if($fetchAnQuUser[$j]["is_correct"] == 1 && $fetchAnQuUser[$j]["selected"] == 1)
		{
			$correctCounter++;
		} else if($fetchAnQuUser[$j]["is_correct"] == -1 && $fetchAnQuUser[$j]["selected"] == -1)
		{
			$correctCounter++;
		}
		if($fetchAnQuUser[$j]["is_correct"] != 0)
		{
			$totalCounter++;
		}
	}
	if($totalCounter != 0)
	{
		$results = "(".$correctCounter."/".$totalCounter.") " . number_format(($correctCounter*100)/$totalCounter, 1) . "%";
	} else
	{
		$results = "-";
	}
	
	$infos["id"] = $id;
	$infos["nextId"] = $nextId;
	$infos["upDownIcon"] = "<img alt='up' src='assets/icon_downUp.png' width='18' height='18' style='cursor: move;'>";
	$infos["questionText"] = "<img width='15' height='15' class='questionTypeInfo' original-title='$qType' style='margin-right: 5px; margin-bottom: 3px;' src='assets/icon_$qType.png'>$text";
	$infos["totalAnswers"] = $totalAnswers;
	$infos["results"] = $results;
	$infos["icons"] = "<a href='?p=createEditQuestion&mode=edit&fromsite=createEditQuiz&quizId=$quizId&id=$id' class='editQuestion' original-title='Frage bearbeiten'>
		        		<img id='editQuestion' src='assets/icon_edit.png' height='18px' width='18px'>
		        	</a>&nbsp;
		            <img id='delQuestionImg' style='cursor: pointer;' class='deleteQuestion delQuestionImg' src='assets/icon_delete.png' original-title='Frage aus dieser Lernkontrolle l&ouml;schen' height='18px' width='18px' onclick='delQuestion($id)'><br />";
	
	return $infos;
}

function deleteExecution()
{
	global $dbh;
	$response_array["status"] = "OK";
	
	if(!isset($_POST["execId"]))
	{
		$response_array["status"] = "error";
		$response_array["text"] = $lang["parameterError"];
	}
	
	if($_SESSION['role']['creator'])
	{
		$stmt = $dbh->prepare("select owner_id, questionnaire.id from questionnaire inner join qunaire_exec on qunaire_exec.questionnaire_id = questionnaire.id 
							inner join execution on qunaire_exec.execution_id = execution.id where execution.id = :id");
		$stmt->bindParam(":id", $_POST["execId"]);
		$stmt->execute();
		$fetchOwnerAndId = $stmt->fetch(PDO::FETCH_ASSOC);

		if($_SESSION["id"] == $fetchOwnerAndId["owner_id"] || $_SESSION['role']['admin'] == 1)
		{
			$stmt = $dbh->prepare("delete from qunaire_exec where execution_id = :execId");
			$stmt->bindParam(":execId", $_POST["execId"]);
			$delQunaire_Exec = $stmt->execute();
			
			$stmt = $dbh->prepare("delete from group_exec where execution_id = :execId");
			$stmt->bindParam(":execId", $_POST["execId"]);
			$delGroup_Exec = $stmt->execute();
			
			$stmt = $dbh->prepare("delete from user_exec where execution_id = :execId");
			$stmt->bindParam(":execId", $_POST["execId"]);
			$delUser_Exec = $stmt->execute();
			
			$stmt = $dbh->prepare("select id from user_exec_session where execution_id = :execId");
			$stmt->bindParam(":execId", $_POST["execId"]);
			$stmt->execute();
			$fetchSessionId = $stmt->fetchAll(PDO::FETCH_ASSOC);
			
			$delAn_qu_user = true;
			for($i = 0; $i < count($fetchSessionId); $i++)
			{
				$stmt = $dbh->prepare("delete from an_qu_user where session_id = :sId");
				$stmt->bindParam(":sId", $fetchSessionId[$i]["id"]);
				if(!$stmt->execute())
					$delAn_qu_user = false;
			}
			
			$stmt = $dbh->prepare("delete from user_exec_session where execution_id = :execId");
			$stmt->bindParam(":execId", $_POST["execId"]);
			$delUser_exec_session = $stmt->execute();
			
			$stmt = $dbh->prepare("delete from execution where id = :execId");
			$stmt->bindParam(":execId", $_POST["execId"]);
			$delExecution = $stmt->execute();
			
			if($delQunaire_Exec && $delGroup_Exec && $delUser_Exec && $delAn_qu_user && $delUser_exec_session && $delExecution)
			{
				$response_array["status"] = "OK";
				$response_array["quizId"] = $fetchOwnerAndId["id"];
				$response_array["execId"] = $_POST["execId"];
			} else {
				$response_array["status"] = "error";
				$response_array["text"] = $lang["DB-Update-Error"];
			}
			
			echo json_encode($response_array);
			exit;
		} else {
			$response_array["status"] = "error";
			$response_array["text"] = $lang["noAccessError"];
			echo json_encode($response_array);
			exit;
		}
	} else {
		$response_array["status"] = "error";
		$response_array["text"] = $lang["noAccessError"];
		echo json_encode($response_array);
		exit;
	}
}

function deleteQuiz()
{
	global $dbh;
	
	$response_array["status"] = "OK";
	
	if(!isset($_POST["quizId"]))
	{
		$response_array["status"] = "error";
		$response_array["text"] = $lang["parameterError"];
	}
	
	if($_SESSION['role']['creator'])
	{
		$stmt = $dbh->prepare("select owner_id from questionnaire where id = :id");
		$stmt->bindParam(":id", $_POST["quizId"]);
		$stmt->execute();
		$fetchOwer = $stmt->fetch(PDO::FETCH_ASSOC);

		if($_SESSION["id"] == $fetchOwer["owner_id"] || $_SESSION['role']['admin'] == 1)
		{
			$stmt = $dbh->prepare("select execution_id from qunaire_exec where questionnaire_id = :qId");
			$stmt->bindParam(":qId", $_POST["quizId"]);
			if($stmt->execute())
			{
				if($stmt->rowCount() != 0) 
				{
					$response_array["status"] = "error";
					$response_array["text"] = $lang["existingExecutionError"];
					echo json_encode($response_array);
					exit;
				}
			}
			
			$stmt = $dbh->prepare("delete from qunaire_exec where questionnaire_id = :qId");
			$stmt->bindParam(":qId", $_POST["quizId"]);
			$delQunaire_exec = $stmt->execute();
			
			$stmt = $dbh->prepare("delete from qunaire_qu where questionnaire_id = :qId");
			$stmt->bindParam(":qId", $_POST["quizId"]);
			$delQunaire_qu = $stmt->execute();

			$stmt = $dbh->prepare("delete from qunaire_assigned_to where questionnaire_id = :qId");
			$stmt->bindParam(":qId", $_POST["quizId"]);
			$delQunaire_assigned_to = $stmt->execute();

			$stmt = $dbh->prepare("delete from questionnaire where id = :qId");
			$stmt->bindParam(":qId", $_POST["quizId"]);
			$delQuestionnaire = $stmt->execute();

			if($delQunaire_exec && $delQunaire_qu && $delQunaire_assigned_to && $delQuestionnaire)
			{
				$response_array["status"] = "OK";
			} else {
				$response_array["status"] = "error";
				$response_array["text"] = $lang["DB-Update-Error"];
			}
				
			echo json_encode($response_array);
			exit;
		} else {
			$response_array["status"] = "error";
			$response_array["text"] = $lang["noAccessError"];
			echo json_encode($response_array);
			exit;
		}
	} else {
		$response_array["status"] = "error";
		$response_array["text"] = $lang["noAccessError"];
		echo json_encode($response_array);
		exit;
	}
}







































//TODO
// insertQuiz ist alt!
function insertQuiz()
{
	global $dbh;
	
	if(isset($_POST["mode"]) && (isset($_POST["btnSave"]) || isset($_POST["btnSaveAsDraft"]) || isset($_POST["btnAddQuestion"])) &&
			isset($_POST["quizText"]) && isset($_POST["topic"]) && isset($_POST["language"]) &&
			isset($_POST["endDate"]) && isset($_POST["endTime"]) &&
			isset($_POST["startDate"]) && isset($_POST["startTime"])
			&& isset($_POST["timeLimitMode"])
			&& isset($_POST["reportAfterQuizResults"])
			&& isset($_POST["reportAfterQuizPoints"])
			&& isset($_POST["quizPriority"])
			&& isset($_POST["amountQuestionMode"])
			&& isset($_POST["maxParticipationsMode"])
			&& isset($_POST["quizPassedMode"])
			&& isset($_POST["singlechoiceMult"])
			&& isset($_POST["noParticipationPeriod"]))
	{
		//check correct owner
		if($_POST["mode"] == 'edit')
		{
			//fetch owner of this quiz
			$stmt = $dbh->prepare("select owner_id from questionnaire where id = :q_id");
			$stmt->bindParam(":q_id", $_POST["quiz_id"]);
			$stmt->execute();
			$fetchQuizOwnerPic = $stmt->fetch(PDO::FETCH_ASSOC);

			//return if it is not the owner of this quiz
			if($fetchQuizOwnerPic["owner_id"] != $_SESSION["id"] && $_SESSION["role"]["admin"] != 1 && !amIAssignedToThisQuiz($dbh, $_POST["quiz_id"]))
			{
				header("Location: ?p=quiz&code=-1&info=asd");
				exit;
			}
		}
			
		//check new Language is not empty
		if($_POST["language"] == "newLanguage")
		{
			if($_POST["newLanguage"] == "")
			{
				header("Location: ?p=createEditQuiz&code=-3&info=lang");
				exit;
			}
		}
			
		//check new Topic is not empty
		if($_POST["topic"] == "newTopic")
		{
			if($_POST["newTopic"] == "")
			{
				header("Location: ?p=createEditQuiz&code=-3&info=topic");
				exit;
			}
		}
			
		//insert quiz
		if($_POST["mode"] == "create")
		{
			// TODO: ob �bernehmen wird f�r den link gebraucht
			//qnaire_token f�r was genau wird dieses Token ben�tigt? Braucht es das wirklich?
			$qnaire_token = NULL;

			do {
				$qnaire_token = substr(md5(uniqid(rand(), true)), 0, 6);
				$stmt = $dbh->prepare("select id from questionnaire where qnaire_token = :qt");
				$stmt->bindParam(":qt", $qnaire_token);
				$stmt->execute();
			} while($stmt->rowCount()>0);

			$stmt = $dbh->prepare("insert into questionnaire (owner_id, subject_id, name, starttime, endtime, qnaire_token, random_questions, random_answers, limited_time, result_visible, result_visible_points, language, amount_of_questions, public, description, creation_date, last_modified, priority, amount_participations, quiz_passed, singlechoice_multiplier, noParticipationPeriod, showTaskPaper)
					values (" . $_SESSION["id"] . ", :subject_id, :name, :starttime, :endtime, :qnaire_token, :random_questions, :random_answers, :limited_time, :result_visible, :result_visible_points, :language, :amount_of_questions, :public, :description, ".time().", ".time().", :priority, :amount_participations, :quiz_passed, :singlechoice_multiplier, :noParticipationPeriod, :showTaskPaper)");

			$stmt->bindParam(":qnaire_token", $qnaire_token);

		} else if($_POST["mode"] == "edit")
		{
			$stmt = $dbh->prepare("update questionnaire set subject_id = :subject_id, name = :name, starttime = :starttime, endtime = :endtime, random_questions = :random_questions, random_answers = :random_answers, limited_time = :limited_time, result_visible = :result_visible, result_visible_points = :result_visible_points,
					language = :language, amount_of_questions = :amount_of_questions, public = :public, description = :description, last_modified = :last_modified, priority = :priority, amount_participations = :amount_participations, quiz_passed = :quiz_passed, singlechoice_multiplier = :singlechoice_multiplier, noParticipationPeriod = :noParticipationPeriod, showTaskPaper = :showTaskPaper where id = :quiz_id");
			$stmt->bindParam(":quiz_id", $_POST["quiz_id"]);
			$stmt->bindParam(":last_modified", time());

		}
			
		//parse/check start / enddate | start / endtime
		$startdate = time();
		$enddate = strtotime('+1 Week');
			
		if(substr_count($_POST["startDate"], '.') == 2 && substr_count($_POST["endDate"], '.') == 2
				&& substr_count($_POST["startTime"], ':') == 1 && substr_count($_POST["endTime"], ':') == 1)
		{
			$arrDate = explode(".", $_POST["startDate"]);
			$arrTime = explode(":", $_POST["startTime"]);

			$startdate = mktime($arrTime[0], $arrTime[1],0,$arrDate[1], $arrDate[0], $arrDate[2]);

			$arrDate = explode(".", $_POST["endDate"]);
			$arrTime = explode(":", $_POST["endTime"]);

			$enddate = mktime($arrTime[0], $arrTime[1],0,$arrDate[1], $arrDate[0], $arrDate[2]);
		} else {
			header("Location: ?p=quiz&code=-3");
			exit;
		}
			
		//Task paper everytime available
		$showQuizTaskPaper = 0;
		if(isset($_POST["showQuizTaskPaper"]))
			$showQuizTaskPaper = 1;
				
			//random_questions
			$rndQuestions = 0;
			if(isset($_POST["randomizeQuestions"]))
				$rndQuestions = 1;
					
				//random_answers
				$rndAnswers = 0;
				if(isset($_POST["randomizeAnswers"]))
					$rndAnswers = 1;
						
					//limited_time
					$limited_time = 0;
					if($_POST["timeLimitMode"] == 1) //limit with $_POST["quizTimeLimit"]
					{
						if(substr_count($_POST["quizTimeLimit"], ':') == 1)
						{
							$arrTime = explode(":", $_POST["quizTimeLimit"]);
							$limited_time = ($arrTime[0]*60) + $arrTime[1];
						} else {
							header("Location: ?p=quiz&code=-3");
							exit;
						}
					}
						
					//result_visible
					$result_visible = 1;
					if($_POST["reportAfterQuizResults"] == 1 || $_POST["reportAfterQuizResults"] == 2 || $_POST["reportAfterQuizResults"] == 3)
						$result_visible = $_POST["reportAfterQuizResults"];
							
						//result_visible_points
						$result_visible_points = 1;
						if($_POST["reportAfterQuizPoints"] == 1 || $_POST["reportAfterQuizPoints"] == 2)
							$result_visible_points = $_POST["reportAfterQuizPoints"];
								
							//amount_of_questions
							$amountOfQuestions = 0;
							if($_POST["amountQuestionMode"] == 1)
							{
								if(!is_numeric($_POST["amountOfQuestions"]))
								{
									header("Location: ?p=quiz&code=-4");
									exit;
								}
								$amountOfQuestions = intval($_POST["amountOfQuestions"]);
							}
								
							//public 2 = entwurf, 1 = public, 0 = private
							$isQuizPublic = 2;
							if(isset($_POST["btnSave"]))
							{
								if(isset($_POST["isPublic"]))
									$isQuizPublic = 1;
									else
										$isQuizPublic = 0;
							}
								
							//amount max participations
							$maxParticipations = 0;
							if($_POST["maxParticipationsMode"] == 1)
							{
								if(!is_numeric($_POST["maxParticipations"]))
								{
									header("Location: ?p=quiz&code=-4");
									exit;
								}
								$maxParticipations = intval($_POST["maxParticipations"]);
							}
								
							//quizPassed
							$quizPassed = 0;
							if($_POST["quizPassedMode"] == 1)
							{
								if(!is_numeric($_POST["quizPassed"]))
								{
									header("Location: ?p=quiz&code=-4");
									exit;
								}
								$quizPassed = intval($_POST["quizPassed"]);
							}
								
							//noParticipationPeriod
							$noParticipationPeriod = 0;
							if($_POST["noParticipationPeriod"] == 1)
								$noParticipationPeriod = 1;
								
								$dbSubject = $_POST["topic"];
								if($dbSubject == 'null' || $dbSubject == 'newTopic')
								{
									$dbSubject = null;
								}
									
								//checkNewLanguage
								$language = $_POST["language"];
								if($_POST["language"] == "newLanguage")
								{
									$language = "English";
								}
									
								$stmt->bindParam(":subject_id", $dbSubject);
								$stmt->bindParam(":name", $_POST["quizText"]);
								$stmt->bindParam(":starttime", $startdate);
								$stmt->bindParam(":endtime", $enddate);
								$stmt->bindParam(":random_questions", $rndQuestions);
								$stmt->bindParam(":random_answers", $rndAnswers);
								$stmt->bindParam(":limited_time", $limited_time);
								$stmt->bindParam(":result_visible", $result_visible);
								$stmt->bindParam(":result_visible_points", $result_visible_points);
								$stmt->bindParam(":language", $language);
								$stmt->bindParam(":amount_of_questions", $amountOfQuestions);
								$stmt->bindParam(":public", $isQuizPublic);
								$stmt->bindParam(":description", $_POST["description"]);
								$stmt->bindParam(":priority", $_POST["quizPriority"]);
								$stmt->bindParam(":amount_participations", $maxParticipations);
								$stmt->bindParam(":quiz_passed", $quizPassed);
								$stmt->bindParam(":singlechoice_multiplier", $_POST["singlechoiceMult"]);
								$stmt->bindParam(":noParticipationPeriod", $noParticipationPeriod);
								$stmt->bindParam(":showTaskPaper", $showQuizTaskPaper);
									
									
								if($stmt->execute())
								{
									$insertedQuizId = $dbh->lastInsertId();

									if($_POST["mode"] == "edit")
									{
										$insertedQuizId = $_POST["quiz_id"];
									}
									
									
									$multipleFiles = $_FILES['btnImportQuestionsFromDirectory'];
									if($multipleFiles["name"][0] != "")
									{
										$excelTemplate = array();
										$uploadedImages = array();
										
										for($i = 0; $i < count($multipleFiles["name"]); $i++)
										{
											if(strtolower(pathinfo($multipleFiles["name"][$i], PATHINFO_EXTENSION)) == "xlsx")
											{
												$excelTemplate["name"] = $multipleFiles["name"][$i];
												$excelTemplate["type"] = $multipleFiles["type"][$i];
												$excelTemplate["tmp_name"] = $multipleFiles["tmp_name"][$i];
												$excelTemplate["error"] = $multipleFiles["error"][$i];
												$excelTemplate["size"] = $multipleFiles["size"][$i];
											} else 
											{
												$image = array();
												$image["name"] = $multipleFiles["name"][$i];
												$image["type"] = $multipleFiles["type"][$i];
												$image["tmp_name"] = $multipleFiles["tmp_name"][$i];
												$image["error"] = $multipleFiles["error"][$i];
												$image["size"] = $multipleFiles["size"][$i];
												array_push($uploadedImages, $image);
											}
										}
										
										foreach($uploadedImages as $image)
										{
											if ($image["error"] > 0) {
												header("Location: ?p=quiz&code=-28");
												exit;
											}
										}
										
										if(!isset($excelTemplate))
										{
											header("Location: ?p=quiz&code=-29");
											exit;
										}
											
									} elseif (isset($_FILES["btnImportQuestionsFromExcel"]) && $_FILES["btnImportQuestionsFromExcel"]["name"] != "")
									{
										if(strtolower(pathinfo($_FILES["btnImportQuestionsFromExcel"]["name"], PATHINFO_EXTENSION)) == "xlsx")
										{
											$excelTemplate["name"] = $_FILES["btnImportQuestionsFromExcel"]["name"];
											$excelTemplate["type"] = $_FILES["btnImportQuestionsFromExcel"]["type"];
											$excelTemplate["tmp_name"] = $_FILES["btnImportQuestionsFromExcel"]["tmp_name"];
											$excelTemplate["error"] = $_FILES["btnImportQuestionsFromExcel"]["error"];
											$excelTemplate["size"] = $_FILES["btnImportQuestionsFromExcel"]["size"];
										}
										
										if(!isset($excelTemplate))
										{
											header("Location: ?p=quiz&code=-29");
											exit;
										}
									}
								
									
									//questions with Excel upload
									if(isset($excelTemplate))
									{
										//error while uploading
										if ($excelTemplate["error"] > 0) {
											header("Location: ?p=quiz&code=-28");
											exit;
										}
										
										include_once 'importExcel.php';
										
										$excelContent = importExcel($excelTemplate);
										$questions = createQuestionArray($excelContent);
										
										//Excel contains no questions
										if(count($questions) == 0)
										{
											header("Location: ?p=quiz&code=-39");
											exit;
										}
										
										foreach($questions as $question)
										{
											if($question->getNumberOfAnswers() < 2)
											{
												header("Location: ?p=quiz&code=-44");
												exit;
											} else if($question->getNumberOfCorrectAnswers() == 0)
											{
												header("Location: ?p=quiz&code=-45");
												exit;
											}
										}
										
										
										if($_POST["addOrReplaceQuestions"] == 0 && $_POST["mode"] == "edit") //replace questions
										{
											//unlink all existing questions
											$stmt = $dbh->prepare("delete from qunaire_qu where questionnaire_id = :qId");
											$stmt->bindParam(":qId", $_POST["quiz_id"]);
											$stmt->execute();
										}
										
										
										$invalidQuestions = array();
										$imageCounter = 1;
										
										foreach($questions as $question)
										{											
											//check if questiontext AND all answers are already there
											//if yes use this id instead of insert the same question
											$stmt = $dbh->prepare("select question.id as qId, question.text as qText from question where question.text = :text");
											$stmt->bindParam(":text", $question->getText());
											$stmt->execute();
											
											$numOfEqualQuestions = $stmt->rowCount();
											$allIn = false;
											
											if($numOfEqualQuestions > 0) //Question already exists
											{
												$stmt = $dbh->prepare("select question.id as qId, question.text as qText, answer.id as aId, answer.text as aText, is_correct from question inner join answer_question on answer_question.question_id = question.id inner join answer on answer.id = answer_question.answer_id where question.text = :text");
												$stmt->bindParam(":text", $question->getText());
												$stmt->execute();
												
												$allInCount = 0;
												$fetchCheckQuestion = $stmt->fetchAll(PDO::FETCH_ASSOC);
												
												//compare all DB-Answers to all Excel-Answers
												for($i = 0; $i < count($fetchCheckQuestion); $i++) //DB-Answers
												{
													for($j = 0; $j < $question->getNumberOfAnswers(); $j++) //Excel-Answers
													{
														$dbQuestionText = $fetchCheckQuestion[$i]["aText"];
														$excelQuestionText = $question->getAnswers()[$j]->getText();
														if($dbQuestionText == $excelQuestionText)
														{
															$allInCount++;
														}
													}
													
													if($allInCount == $question->getNumberOfAnswers())
													{
														$allIn = true;
													}
												}
											}											
											
											if(!$allIn)
											{
												//Singlechoice with != 1 correct Answers
												if(!$question->isValid())
												{
													array_push($invalidQuestions, htmlspecialchars($question->getText()));
												}
											
												$language = "Deutsch";
												if($_SESSION["language"] == "en")
												{
													$language = "English";
												}
												
												$imageName = $question->getImage();
												$uploadedImagePath = null;
												if(isset($imageName))
												{
													foreach($uploadedImages as $uploadedImage)
													{
														if($uploadedImage["name"] == $imageName)
														{
															//Check if file is image
															if(!getimagesize($uploadedImage["tmp_name"]))
															{
																header("Location: ?p=quiz&code=-43");
																exit;
															}
															$uploadedQuestionImage = $uploadedImage;
															
														}
													}
													
													if(!isset($uploadedQuestionImage))
													{
														header("Location: ?p=quiz&code=-41");
														exit;
													}
																										
													$uploadedImageFileType = strtolower(pathinfo($uploadedQuestionImage["name"], PATHINFO_EXTENSION));
													$uploadedImagePath = "uploadedImages/" . "question_" . date("d_m_y_H_i_s", time()) . "__" . $imageCounter . "__" . $_SESSION["id"] . "." . $uploadedImageFileType;
													if(!move_uploaded_file($uploadedQuestionImage["tmp_name"], $uploadedImagePath))
													{
														header("Location: ?p=quiz&code=-42");
														exit;
													}
													
													$imageCounter++;
													
												}
												
												
												//insert Question
												$stmt = $dbh->prepare("insert into question	(text, owner_id, type_id, subject_id, language, creation_date, public, last_modified, picture_link) values (:text, :owner_id, :type_id, :subject_id, :language, ".time().", :public, ".time().", :picLink)");
												$stmt->bindParam(":text", $question->getText());
												$stmt->bindParam(":owner_id", $_SESSION["id"]);
												$stmt->bindParam(":type_id", $question->getTypeCode());
												$stmt->bindValue(":subject_id", NULL);
												$stmt->bindValue(":language", $language);
												$stmt->bindValue(":public", 0);
												$stmt->bindValue(":picLink", $uploadedImagePath);
												if(!$stmt->execute())
												{
													header("Location: ?p=quiz&code=-31");
													exit;
												}
												
												$insertedQuestionId = $dbh->lastInsertId();
										
												//insert all Answers
												foreach($question->getAnswers() as $answer)
												{
													$stmt = $dbh->prepare("insert into answer (text) values (:text)");
													$stmt->bindParam(":text", $answer->getText());
													if(!$stmt->execute())
													{
														header("Location: ?p=quiz&code=-32");
														exit;
													}
													
													$insertedAnswerId = $dbh->lastInsertId();
													$answerNumber = 0;
													
													if($question->getTypeCode() == 1) //Singlechoice
													{
														if($answer->isCorrect())
														{
															$isCorrect = 1;
														} else {
															$isCorrect = 0;
														}
													} else { //Multiplechoice
														if($answer->isCorrect())
														{
															$isCorrect = 1;
														} else {
															$isCorrect = -1;
														}
													}
													
													$stmt = $dbh->prepare("insert into answer_question values (:answer_id, :question_id, :is_correct, :order)");
													$stmt->bindParam(":answer_id", $insertedAnswerId);
													$stmt->bindParam(":question_id", $insertedQuestionId);
													$stmt->bindParam(":is_correct", $isCorrect);
													$stmt->bindValue(":order", $answerNumber);
																										
													if(!$stmt->execute())
													{
														header("Location: ?p=quiz&code=-33");
														exit;
													}
													$answerNumber++;
												}
											} else
											{
												$insertedQuestionId = $fetchCheckQuestion[0]["qId"];
											}
											
											$orderCounter = 0;
											$stmt = $dbh->prepare("insert into qunaire_qu (questionnaire_id, question_id, `order`) values (:questionnaire_id, :question_id, :order)");
											$stmt->bindParam(":questionnaire_id", $insertedQuizId);
											$stmt->bindParam(":question_id", $insertedQuestionId);
											$stmt->bindParam(":order", $orderCounter);
											
											if(!$stmt->execute())
											{
												header("Location: ?p=quiz&code=-34"); 
												exit;
											}
											$orderCounter++;
											
										}
									}
									
									
									//requested language
									if($_POST["language"] == "newLanguage")
									{
										$stmt = $dbh->prepare("insert into language_request (user_id, language, timestamp, questionnaire_id) values (:user_id, :language, :timestamp, :questionnaire_id)");
										$stmt->bindParam(":user_id", $_SESSION["id"]);
										$stmt->bindParam(":language", $_POST["newLanguage"]);
										$stmt->bindParam(":timestamp", time());
										$stmt->bindParam(":questionnaire_id", $insertedQuizId);
										$stmt->execute();
									}

									//requested topic
									if($_POST["topic"] == "newTopic")
									{
										$stmt = $dbh->prepare("insert into topic_request (user_id, topic, timestamp, questionnaire_id) values (:user_id, :topic, :timestamp, :questionnaire_id)");
										$stmt->bindParam(":user_id", $_SESSION["id"]);
										$stmt->bindParam(":topic", $_POST["newTopic"]);
										$stmt->bindParam(":timestamp", time());
										$stmt->bindParam(":questionnaire_id", $insertedQuizId);
										$stmt->execute();
									}

									if(isset($_POST["btnAddQuestion"]))
									{
										header("Location: ?p=addQuestions&quizId=" . $insertedQuizId);
									} else if(isset($_POST["btnSave"]))
									{
										$qwnav = "";
										if(count($invalidQuestions) > 0)
										{
											$qwnav = "&qwnav=" . implode(",", $invalidQuestions);
											header("Location: ?p=quiz&code=-40&qwna=".count($invalidQuestions) . $qwnav."&info=");
										} else
										{
											header("Location: ?p=quiz&code=1");
										}
									} else if(isset($_POST["btnSaveAsDraft"]))
									{
										header("Location: ?p=quiz&code=2");
									}
								} else {
									header("Location: ?p=quiz&code=-7&info=" . $case);
								}
									
	} else {
		$info = $_POST["mode"] . " " . (isset($_POST["btnSave"])  ||  isset($_POST["btnSaveAsDraft"])  || isset($_POST["btnAddQuestion"]))  . " " .
				$_POST["quizText"] . " " . $_POST["topic"] . " " . $_POST["language"] . " " .
				$_POST["endDate"] . " " . $_POST["endTime"]  . " " .
				$_POST["startDate"] . " " . $_POST["startTime"] . " " .
				$_POST["timeLimitMode"] . " " .
				$_POST["reportAfterQuestion"] . " " .$_POST["quizPriority"] . " " .
				$_POST["amountQuestionMode"] . " " . $_POST["maxParticipationsMode"];
				header("Location: ?p=quiz&code=-2&info=" .$info);
	}
}


function addQuestionToQuiz()
{
	global $dbh;
	$response_array["status"] = "success";
	
	if(!isset($_POST["quizId"]) || !isset($_POST["questionId"]) || !isset($_POST["checked"]))
	{
		$response_array["status"] = "error";
		$response_array["text"] = "Not all parameters received";
	}
	
	//OWNER?
	$stmt = $dbh->prepare("select owner_id from questionnaire where id = :qId");
	$stmt->bindParam(":qId", $_POST["quizId"]);
	$stmt->execute();
	$fetchQnaireNameOwner = $stmt->fetch(PDO::FETCH_ASSOC);
	
	if($fetchQnaireNameOwner["owner_id"] != $_SESSION["id"] && $_SESSION["role"]["admin"] != 1)
	{
		$response_array["status"] = "error";
		$response_array["text"] = "Not owner or administrator";
	}
	
	if($_POST["checked"] == "true") //Add question
	{
		$stmt = $dbh->prepare("select count(question_id) as total from qunaire_qu where questionnaire_id = :qunaire_id");
		$stmt->bindParam(":qunaire_id", $_POST["quizId"]);
		$stmt->execute();
		$fetchTotal = $stmt->fetch(PDO::FETCH_ASSOC);
		$nextOrder = $fetchTotal["total"];
		
		$stmt = $dbh->prepare("insert into qunaire_qu values (:qunaire_id, :question_id, :order)");
		$stmt->bindParam(":qunaire_id", $_POST["quizId"]);
		$stmt->bindParam(":question_id", $_POST["questionId"]);
		$stmt->bindParam(":order", $nextOrder);
		
		if(!$stmt->execute())
		{
			$response_array["status"] = "error";
			$response_array["text"] = "Couldn't add question to DB";
		}
		
		$response_array["text"] = "Added Question";
	} else { //Remove question
		
		//get current order from current question
		$stmt = $dbh->prepare("select `order` from qunaire_qu where question_id = :questionId");
		$stmt->bindParam(":questionId", $_POST["questionId"]);
		$stmt->execute();
		$fetchQuestionOrder = $stmt->fetch(PDO::FETCH_ASSOC);
		$deletedQuestionOrder = $fetchQuestionOrder["order"];
		
		
		$stmt = $dbh->prepare("delete from qunaire_qu where question_id = :question_id");
		$stmt->bindParam(":question_id", $_POST["questionId"]);
		
		if(!$stmt->execute())
		{
			$response_array["status"] = "error";
			$response_array["text"] = "Couldn't remove question from DB";
		}
		

		//update order from other questions
		$stmt = $dbh->prepare("select question_id, `order` from qunaire_qu where questionnaire_id = :qunaireId");
		$stmt->bindParam(":qunaireId", $_POST["quizId"]);
		$stmt->execute();
		$fetchQuizQuestions = $stmt->fetchAll(PDO::FETCH_ASSOC);
			
		for($i = 0; $i < count($fetchQuizQuestions); $i++)
		{
			if($deletedQuestionOrder > $fetchQuizQuestions[$i]["order"])
			{
				continue;
			}
			
			$stmt = $dbh->prepare("update qunaire_qu set `order` = :order where question_id = :questionId");
			$newOrder = $fetchQuizQuestions[$i]["order"] - 1;
			$stmt->bindParam(":order", $newOrder);
			$stmt->bindParam(":questionId", $fetchQuizQuestions[$i]["question_id"]);
		
			if(! $stmt->execute())
			{
				$response_array["status"] = "error";
				$response_array["text"] = "Couldn't update order";
				return $response_array;
			}
		}
		
		$response_array["text"] = "Removed Question";
	}
	
	echo json_encode($response_array);
	exit;
}


function deleteQuestionFromQuiz()
{
	global $dbh;
	
	if($_SESSION['role']['creator'])
	{
		$stmt = $dbh->prepare("select owner_id from questionnaire where id = :id");
		$stmt->bindParam(":id", $_GET["questionnaireId"]);
		$stmt->execute();
		$fetchOwer = $stmt->fetch(PDO::FETCH_ASSOC);
	
		if($_SESSION["id"] == $fetchOwer["owner_id"] || $_SESSION['role']['admin'] == 1 || amIAssignedToThisQuiz($dbh, $_GET["questionnaireId"]))
		{
			//get current order from current question
			$stmt = $dbh->prepare("select `order` from qunaire_qu where question_id = :questionId");
			$stmt->bindParam(":questionId", $_GET["questionId"]);
			$stmt->execute();
			$fetchQuestionOrder = $stmt->fetch(PDO::FETCH_ASSOC);
			$deletedQuestionOrder = $fetchQuestionOrder["order"];
			
			$stmt = $dbh->prepare("delete from qunaire_qu where questionnaire_id = :questionnaireId and question_id = :questionId");
			$stmt->bindParam(":questionnaireId", $_GET["questionnaireId"]);
			$stmt->bindParam(":questionId", $_GET["questionId"]);
			if($stmt->execute())
			{
				
				//update order from other questions
				$stmt = $dbh->prepare("select question_id, `order` from qunaire_qu where questionnaire_id = :qunaireId");
				$stmt->bindParam(":qunaireId", $_GET["questionnaireId"]);
				$stmt->execute();
				$fetchQuizQuestions = $stmt->fetchAll(PDO::FETCH_ASSOC);
					
				for($i = 0; $i < count($fetchQuizQuestions); $i++)
				{
					if($deletedQuestionOrder > $fetchQuizQuestions[$i]["order"])
					{
						continue;
					}
						
					$stmt = $dbh->prepare("update qunaire_qu set `order` = :order where question_id = :questionId");
					$newOrder = $fetchQuizQuestions[$i]["order"] - 1;
					$stmt->bindParam(":order", $newOrder);
					$stmt->bindParam(":questionId", $fetchQuizQuestions[$i]["question_id"]);
				
					if(! $stmt->execute())
					{
						echo "failed";
					}
				}
				
				echo "ok";
				
			} else {echo "failed";}
		} else {echo "failed";}
	} else {echo "failed";}
}

function moveQuestion()
{
	global $dbh;
	
	if($_SESSION['role']['creator'])
	{
		$stmt = $dbh->prepare("select owner_id from questionnaire where id = :id");
		$stmt->bindParam(":id", $_GET["questionaireId"]);
		$stmt->execute();
		$fetchOwer = $stmt->fetch(PDO::FETCH_ASSOC);
	
		if($_SESSION["id"] == $fetchOwer["owner_id"] || $_SESSION['role']['admin'] == 1 || amIAssignedToThisQuiz($dbh, $_GET["questionaireId"]))
		{
			$qOrders=json_decode($_GET["qOrder"]);
			for ($i = 0; $i < count($qOrders); $i++) {
				$stmt = $dbh->prepare("update qunaire_qu set `order` = :order where questionnaire_id = :qunaireId and question_id = :qId");
				$stmt->bindParam(":order", $i);
				$stmt->bindParam(":qunaireId", $_GET["questionaireId"]);
				$stmt->bindParam(":qId", $qOrders[$i]);
				if(!$stmt->execute())
					echo "failed";
			}
			echo "ok";
		} else {echo "failed";}
	} else {echo "failed";}
}


?>