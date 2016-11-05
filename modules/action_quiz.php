<?php

function deletePicture()
{
	global $dbh;
	
	$stmt = $dbh->prepare("select owner_id from question where id = :question_id");
	$stmt->bindParam(":question_id", $_GET["questionId"]);
	$stmt->execute();
	$fetchQuestionOwner = $stmt->fetch(PDO::FETCH_ASSOC);

	if(($_SESSION['role']['creator'] && $fetchQuestionOwner["owner_id"] == $_SESSION["id"]) || $_SESSION['role']['admin'])
	{
		$stmt = $dbh->prepare("update question set picture_link = NULL where id = :question_id");
		$stmt->bindParam(":question_id", $_GET["questionId"]);
		$stmt->execute();
		if($stmt->execute())
		{
			echo "deletePictureOk";
		} else
		{
			echo "deletePictureFail";
		}
	} else
	{
		echo "deletePictureFail2";
	}
}

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
			&& isset($_POST["singlechoiseMult"])
			&& isset($_POST["noParticipationPeriod"]))
	{
		//check correct owner
		if($_POST["mode"] == 'edit')
		{
			//fetch owner of this quiz
			$stmt = $dbh->prepare("select owner_id, picture_link from questionnaire where id = :q_id");
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
			//qnaire_token
			$qnaire_token = NULL;

			do {
				$qnaire_token = substr(md5(uniqid(rand(), true)), 0, 6);
				$stmt = $dbh->prepare("select id from questionnaire where qnaire_token = :qt");
				$stmt->bindParam(":qt", $qnaire_token);
				$stmt->execute();
			} while($stmt->rowCount()>0);

			$stmt = $dbh->prepare("insert into questionnaire (owner_id, subject_id, name, starttime, endtime, qnaire_token, random_questions, random_answers, limited_time, result_visible, result_visible_points, language, amount_of_questions, public, description, picture_link, creation_date, last_modified, priority, amount_participations, quiz_passed, singlechoise_multiplier, noParticipationPeriod, showTaskPaper)
					values (" . $_SESSION["id"] . ", :subject_id, :name, :starttime, :endtime, :qnaire_token, :random_questions, :random_answers, :limited_time, :result_visible, :result_visible_points, :language, :amount_of_questions, :public, :description, :picLink, ".time().", ".time().", :priority, :amount_participations, :quiz_passed, :singlechoise_multiplier, :noParticipationPeriod, :showTaskPaper)");

			$stmt->bindParam(":qnaire_token", $qnaire_token);

		} else if($_POST["mode"] == "edit")
		{
			$stmt = $dbh->prepare("update questionnaire set subject_id = :subject_id, name = :name, starttime = :starttime, endtime = :endtime, random_questions = :random_questions, random_answers = :random_answers, limited_time = :limited_time, result_visible = :result_visible, result_visible_points = :result_visible_points,
					language = :language, amount_of_questions = :amount_of_questions, public = :public, description = :description, picture_link = :picLink, last_modified = :last_modified, priority = :priority, amount_participations = :amount_participations, quiz_passed = :quiz_passed, singlechoise_multiplier = :singlechoise_multiplier, noParticipationPeriod = :noParticipationPeriod, showTaskPaper = :showTaskPaper where id = :quiz_id");
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
									
									
								//pictureLink
								//fileupload
								if(isset($_FILES["quizLogo"]) && $_FILES["quizLogo"]["name"] != "")
								{
									$subCode = 0;
									//upload picture
									$imageFileType = pathinfo($_FILES["quizLogo"]["name"], PATHINFO_EXTENSION);
									$targetDir = "uploadedImages/";
									$targetFile = $targetDir . "quiz_" . date("d_m_y_H_i_s", time()) . "__" . $_SESSION["id"] . "." . $imageFileType;
									$uploadOk = true;
										
									//check File is an image
									if(!getimagesize($_FILES["quizLogo"]["tmp_name"]))
									{
										$uploadOk = false;
										$subCode = -8;
									}
									//check if file already exists
									if(file_exists($targetFile))
									{
										$uploadOk = false;
										$subCode = -9;
									}
									//check size
									if($_FILES["quizLogo"]["size"] > 20000000)
									{
										$uploadOk = false;
										$subCode = -10;
									}
									//check file format | .jpeg,.jpg,.bmp,.png,.gif
									$imageFileType = strtolower($imageFileType);
									if($imageFileType != "jpg" && $imageFileType != "png" && $imageFileType != "jpeg" && $imageFileType != "gif" && $imageFileType != "bmp")
									{
										$uploadOk = false;
										$subCode = -11;
									}
									//check if all ok?
									if($uploadOk)
									{
										if(!move_uploaded_file($_FILES["quizLogo"]["tmp_name"], $targetFile))
										{
											header("Location: ?p=quiz&code=-6");
											exit;
										}
									} else {
										header("Location: ?p=quiz&code=" . $subCode);
										exit;
									}
								}
									
								$dbNull = NULL;
									
								if($_POST["mode"] == "create")
								{
									if(isset($_FILES["quizLogo"]) && $_FILES["quizLogo"]["name"] != "")
										$stmt->bindParam(":picLink", $targetFile);
										else
											$stmt->bindParam(":picLink", $dbNull);
								} else if($_POST["mode"] == "edit")
								{
									if(isset($_FILES["quizLogo"]) && $_FILES["quizLogo"]["name"] != "")
										$stmt->bindParam(":picLink", $targetFile);
										else
											$stmt->bindParam(":picLink", $fetchQuizOwnerPic["picture_link"]);
								}
								//end
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
								$stmt->bindParam(":singlechoise_multiplier", $_POST["singlechoiseMult"]);
								$stmt->bindParam(":noParticipationPeriod", $noParticipationPeriod);
								$stmt->bindParam(":showTaskPaper", $showQuizTaskPaper);
									
									
								if($stmt->execute())
								{
									$insertedQuizId = $dbh->lastInsertId();

									if($_POST["mode"] == "edit")
									{
										$insertedQuizId = $_POST["quiz_id"];
									}


									//questions with csv upload
									if(isset($_FILES["btnImportQuestionsFromCSV2"]) && $_FILES["btnImportQuestionsFromCSV2"]["name"] != "")
									{
										if ($_FILES["file"]["error"] > 0) {
											header("Location: ?p=quiz&code=-28");
											exit;
										}
										$imageFileType = pathinfo($_FILES["btnImportQuestionsFromCSV2"]["name"], PATHINFO_EXTENSION);
										$imageFileType = strtolower($imageFileType);
										if($imageFileType != "csv")
										{
											header("Location: ?p=quiz&code=-29");
											exit;
										}

										if($_POST["addOrReplaceQuestions"] == 0 && $_POST["mode"] == "edit") //replace questions
										{
											//unlink all existing questions
											$stmt = $dbh->prepare("delete from qunaire_qu where questionnaire_id = :qId");
											$stmt->bindParam(":qId", $_POST["quiz_id"]);
											$stmt->execute();
										}
											
										$questionUploadFileName = $_FILES["btnImportQuestionsFromCSV2"]["name"];
											
										move_uploaded_file($_FILES["btnImportQuestionsFromCSV2"]["tmp_name"], "uploadedImages/" . $questionUploadFileName);
											
										$questionUploadFileName = "uploadedImages/".$questionUploadFileName;
											
										if (($handle = fopen($questionUploadFileName, "r")) !== FALSE) {

											$orderCounter = 0;
											$amountQuestionsWithNoRightAnswer = 0;
											$amountQuestionsWithNoRightAnswerWhichOne = [];
											$firstline = true;
											$csvAnswerStart = false;
											$csvNumber = $csvKeyword = -1;
											$csvQuestion = 0;
											$csvAnswer = 1;
											while (($data = fgetcsv($handle, 0, ";")) !== FALSE) {
													
												for($j = 0; $j < count($data); $j++) //all answers in scv
												{
													$data[$j] = mb_convert_encoding($data[$j], "UTF-8");
													if($firstline && !$csvAnswerStart)
													{
														$headingData = checkStringIn(strtolower($data[$j]));
														if($headingData[0])
														{
															switch ($headingData[1])
															{
																case 0:
																	$csvNumber = $j;
																	break;
																case 1:
																	$csvQuestion = $j;
																	break;
																case 2:
																	$csvAnswer = $j;
																	$csvAnswerStart = true;
																	break;
																case 3:
																	$csvKeyword = $j;
																	break;
															}
														}
													}
												}
													
												//check first line if its a header "question / Answer" line
												if($firstline && (strpos(strtolower($data[0]), "question") !== false || strpos(strtolower($data[0]), "frage") !== false || strpos(strtolower($data[1]), "answer") !== false || strpos(strtolower($data[1]), "antwort") !== false ))
												{
													$firstline = false;
													continue;
												}
													
												//check if questiontext AND all answers are already there
												//if yes use this id instead of insert the same question
													
												$stmt = $dbh->prepare("select question.id as qId, question.text as qText, answer.id as aId, answer.text as aText, is_correct from question inner join answer_question on answer_question.question_id = question.id inner join answer on answer.id = answer_question.answer_id where question.text = :text");
												$stmt->bindParam(":text", $data[$csvQuestion]);
												$stmt->execute();
												$allIn = false;
												$fetchCheckQuestion = null;
												$questionCheckRowCount = $stmt->rowCount();
												if($questionCheckRowCount > 0) //Question already exists
												{
													$allInCount = 0;
													$fetchCheckQuestion = $stmt->fetchAll(PDO::FETCH_ASSOC);
													for($i = 0; $i < count($fetchCheckQuestion); $i++) //all answers- from query inc. questionstring
													{
														for($j = $csvAnswer; $j < count($data); $j++) //all answers in scv
														{
															if($fetchCheckQuestion[$i]["aText"] == str_replace("*", "", $data[$j]))
															{
																$allInCount++;
															}
														}
													}
													if($questionCheckRowCount == $allInCount)
														$allIn = true;
												}
													
												if(!$allIn)
												{
													$amountofCorrectAnswers = 0;
													for($i = $csvAnswer; $i < count($data); $i++)
													{
														if(strpos($data[$i], "*") == true && (strlen($data[$i])-1 == strpos($data[$i], "*") || strlen($data[$i])-2 == strpos($data[$i], "*")))
														{
															$amountofCorrectAnswers++;
														}
													}

													if($amountofCorrectAnswers == 0)
													{
														$amountQuestionsWithNoRightAnswer++;
														array_push($amountQuestionsWithNoRightAnswerWhichOne, htmlspecialchars($data[$csvQuestion]));
													}

													$type_id = 1;
													if($amountofCorrectAnswers > 1)
														$type_id = 2;

														$stmt = $dbh->prepare("insert into question	(text, owner_id, type_id, subject_id, language, creation_date, public, last_modified, picture_link)
								values (:text, ". $_SESSION["id"] .", :type_id, :subject_id, :language, ".time().", :public, ".time().", :picLink)");
														$stmt->bindParam(":text", $data[$csvQuestion]);
														$stmt->bindParam(":type_id", $type_id);
														$stmt->bindValue(":subject_id", NULL);
														$stmt->bindValue(":language", "Deutsch");
														$stmt->bindValue(":public", 0);
														$stmt->bindValue(":picLink", NULL);
														if(!$stmt->execute())
														{
															header("Location: ?p=quiz&code=-31");
															exit;
														}
														$insertedQuestionId = $dbh->lastInsertId();

														for($i = $csvAnswer; $i < count($data); $i++)
														{
															if($data[$i] == "")
																continue;
																$isCorrect = false;
																$answerInsertText = $data[$i];
																$stmt = $dbh->prepare("insert into answer (text) values (:text)");
																if(strpos($data[$i], "*", strlen($data[$i]) - 2) !== false)
																{
																	$isCorrect = true;
																	$answerInsertText = substr($data[$i], 0, strpos($data[$i], "*", strlen($data[$i]) - 2));
																}
																$stmt->bindParam(":text", $answerInsertText);
																if(!$stmt->execute())
																{
																	header("Location: ?p=quiz&code=-32");
																	exit;
																}
																	
																$insertedAnswerId = $dbh->lastInsertId();
																	
																$stmt = $dbh->prepare("insert into answer_question values (:answer_id, :question_id, :is_correct, :order)");
																$stmt->bindParam(":answer_id", $insertedAnswerId);
																$stmt->bindParam(":question_id", $insertedQuestionId);
																if($type_id == 2 && $isCorrect == false)
																	$isCorrect = -1;
																	$stmt->bindParam(":is_correct", $isCorrect);
																	$stmt->bindValue(":order", ($i-1));
																	if(!$stmt->execute())
																	{
																		header("Location: ?p=quiz&code=-33");
																		exit;
																	}
														}
												} else {
													$insertedQuestionId = $fetchCheckQuestion[0]["qId"];
												}
													
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
											fclose($handle);
											unlink($questionUploadFileName);

										} else {
											header("Location: ?p=quiz&code=-30&info=".$questionUploadFileName);
											exit;
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
										if($amountQuestionsWithNoRightAnswer > 0)
										{
											$qwnav = "&qwnav=" . implode(",", $amountQuestionsWithNoRightAnswerWhichOne);
										}
										header("Location: ?p=quiz&code=1&qwna=".$amountQuestionsWithNoRightAnswer . $qwnav."&info=");
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


function addQuestions()
{
	global $dbh;
	
	//addQuestions to Quiz
	//questions[]
	if(!isset($_POST["quizId"]))
	{
		header("Location: ?p=quiz&code=-2");
		exit;
	}
	
	//OWNER?
	$stmt = $dbh->prepare("select name, owner_id from questionnaire where id = :qId");
	$stmt->bindParam(":qId", $_POST["quizId"]);
	$stmt->execute();
	$fetchQnaireNameOwner = $stmt->fetch(PDO::FETCH_ASSOC);
	
	if($fetchQnaireNameOwner["owner_id"] != $_SESSION["id"] && $_SESSION["role"]["admin"] != 1)
	{
		header("Location: ?p=quiz&code=-1&info=ert");
		exit;
	}
	
	if (!isset($_POST["questions"]))
	{
		header("Location: ?p=quiz&code=-12");
		exit;
	}
	
	$stmt = $dbh->prepare("delete from qunaire_qu where questionnaire_id = :qunaire_id");
	$stmt->bindParam(":qunaire_id", $_POST["quizId"]);
	$stmt->execute();
		
	foreach($_POST["questions"] as $value){
			
		$stmt = $dbh->prepare("insert into qunaire_qu (questionnaire_id, question_id) values (:qunaire_id, :q_id)");
		$stmt->bindParam(":qunaire_id", $_POST["quizId"]);
		$stmt->bindParam(":q_id", $value);
			
		if(!$stmt->execute())
		{
			header("Location: ?p=quiz&code=-13");
			exit;
		}
			
	}
	
	header("Location: ?p=createEditQuiz&mode=edit&id=" . $_POST["quizId"]);
}


function deleteQuestionFromQuiz()
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
			$stmt = $dbh->prepare("delete from qunaire_qu where questionnaire_id = :questionnaireId and question_id = :questionId");
			$stmt->bindParam(":questionnaireId", $_GET["questionaireId"]);
			$stmt->bindParam(":questionId", $_GET["questionId"]);
			if($stmt->execute())
			{
				echo "ok";
			} else {echo "failed";}
		} else {echo "failed";}
	} else {echo "failed";}
}


function deleteQuiz()
{
	global $dbh;
	
	if($_SESSION['role']['creator'])
	{
		$stmt = $dbh->prepare("select owner_id from questionnaire where id = :id");
		$stmt->bindParam(":id", $_GET["quizId"]);
		$stmt->execute();
		$fetchOwer = $stmt->fetch(PDO::FETCH_ASSOC);
	
		if($_SESSION["id"] == $fetchOwer["owner_id"] || $_SESSION['role']['admin'] == 1)
		{
			$stmt = $dbh->prepare("delete from qunaire_qu where questionnaire_id = :qId");
			$stmt->bindParam(":qId", $_GET["quizId"]);
			$delQunaire_qu = $stmt->execute();
	
			$stmt = $dbh->prepare("select id from user_qunaire_session where questionnaire_id = :qId");
			$stmt->bindParam(":qId", $_GET["quizId"]);
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
	
			$stmt = $dbh->prepare("delete from user_qunaire_session where questionnaire_id = :qId");
			$stmt->bindParam(":qId", $_GET["quizId"]);
			$delUser_qunaire_session = $stmt->execute();
	
			$stmt = $dbh->prepare("delete from qunaire_assigned_to where questionnaire_id = :qId");
			$stmt->bindParam(":qId", $_GET["quizId"]);
			$delQunaire_assigned_to = $stmt->execute();
	
			$stmt = $dbh->prepare("delete from questionnaire where id = :qId");
			$stmt->bindParam(":qId", $_GET["quizId"]);
			$delQuestionnaire = $stmt->execute();
	
			if($delQunaire_qu && $delUser_qunaire_session && $delQuestionnaire && $delAn_qu_user && $delQunaire_assigned_to)
			{
				echo "deleteQuizOk";
			} else {
				echo "failed";
			}
		}
	}
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