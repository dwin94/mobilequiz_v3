<?php
session_start();

ob_end_clean();
include_once(__DIR__ . '/../config/config.php');
include "modules/extraFunctions.php";
require_once('helper/tcpdf_min/tcpdf_import.php');

function getMultiplechoiceChar($val)
{
	switch ($val)
	{
		case -1:
			return '✗';
			break;
		case 0:
			return '-';
			break;
		case 1:
			return '✓';
			break;
	}
}

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

$uId = $_SESSION["id"];
if(isset($_GET["uId"]))
{
	$uId = $_GET["uId"];
}

$pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

$pdf->setImageScale(5);

$fontname = TCPDF_FONTS::addTTFfont('helper/tcpdf_min/fonts/Arial_Unicode_MS.ttf', 'TrueTypeUnicode', 'UTF-8');

if($action == "getQuizTaskPaper" || $action == "getQuizTaskPaperWithMyAnswers")
{
	if(!isset($_GET["execId"]))
	{
		header("Location: ?p=quiz&code=-36&info=noQuizId");
		exit;
	}
	
	$fontSize = 16;
	$w = 0;
	$l = 18;
	
	$stmt = $dbh->prepare("select questionnaire.id as qId, questionnaire.name, questionnaire.description, starttime, endtime, result_visible, firstname, lastname, email, owner_id, noParticipationPeriod 
						from questionnaire inner join user on user.id = questionnaire.owner_id inner join user_data on user_data.user_id = user.id 
						inner join qunaire_exec on qunaire_exec.questionnaire_id = questionnaire.id inner join execution on qunaire_exec.execution_id = execution.id 
						where execution.id = :execId");
	$stmt->bindParam(":execId", $_GET["execId"]);
	if(!$stmt->execute())
	{
		header("Location: ?p=quiz&code=-36&info=DbError");
		exit;
	}
	if($stmt->rowCount() != 1)
	{
		header("Location: ?p=quiz&code=-36&info=noQuizWithThatId");
		exit;
	}
	$fetchQuiz = $stmt->fetch(PDO::FETCH_ASSOC);
	

	if($_SESSION['role']['admin'] != 1)
	{
		if(!doThisQuizHaveAGroupRestrictionAndAmIInThisGroup($dbh, $fetchQuiz["qId"]))
		{
			header("Location: index.php?p=quiz&code=-38");
			exit;
		}
	}
	
	$stmt = $dbh->prepare("select id from user_exec_session where user_id = :user_id and execution_id = :execId");
	$stmt->bindParam (":user_id", $uId);
	$stmt->bindParam (":execId", $_GET["execId"]);
	$stmt->execute();
	$ownParticipationAmount = $stmt->rowCount();

	if($_SESSION["role"]["admin"] != 1 && $_SESSION["id"] != $fetchQuiz["owner_id"] && !amIAssignedToThisQuiz($dbh, $fetchQuiz["qId"]))
	{
		if($action == "getQuizTaskPaperWithMyAnswers" &&
				!(((time() > $fetchQuiz["endtime"] || $fetchQuiz["result_visible"] == 1) &&
				$fetchQuiz["result_visible"] != 3))) {
			
					header("Location: ?p=quiz&code=-36&info=noAccess");
					exit;
			
		}
		if($fetchQuiz["result_visible"] == 3)
		{
			header("Location: ?p=quiz&code=-36&info=noAccess2");
			exit;
		}
		if($fetchQuiz["starttime"] > time() && $fetchQuiz["noParticipationPeriod"] == 0)
		{
			header("Location: ?p=quiz&code=-36&info=noAccess3");
			exit;
		}
		if(!($ownParticipationAmount > 0))
		{
			header("Location: ?p=quiz&code=-36&info=noAccess4");
			exit;
		}
	}
	
	// set auto page breaks
	$pdf->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);

	// set default font subsetting mode
	$pdf->setFontSubsetting(true);
	
	// add a page
	$pdf->AddPage();
	
	// set font
	$pdf->SetFont('helvetica', 'B', $fontSize);
	if($action == "getQuizTaskPaper") {
		$pdf->Cell($w,$l,'Aufgabenblatt der Lernkontrolle: ' . $fetchQuiz["name"], 0, 1);
	} else {
		$pdf->Cell($w,$l,'Ihr Ergebnis der Lernkontrolle: ' . $fetchQuiz["name"], 0, 1);
	}
	
	$fontSize = 10;
	$w = 55;
	$l = 6;
	
	$pdf->SetFont('helvetica','B',$fontSize);
	$pdf->Cell($w,$l,'Name der Lernkontrolle:');
	$pdf->SetFont('');
	$pdf->Cell($w,$l,$fetchQuiz["name"], 0, 1);
	
	$pdf->SetFont('helvetica','B',$fontSize);
	$pdf->Cell($w,$l,'Beschreibung:');
	$pdf->SetFont('');
	$pdf->Cell($w,$l,$fetchQuiz["description"], 0, 1);
	
	$pdf->SetFont('helvetica','B',$fontSize);
	$pdf->Cell($w,$l,'Startzeitpunkt:');
	$pdf->SetFont('');
	$pdf->Cell($w,$l,date("d. F Y H:i:s", $fetchQuiz["starttime"]), 0, 1);
	
	$pdf->SetFont('helvetica','B',$fontSize);
	$pdf->Cell($w,$l,'Endzeitpunkt:');
	$pdf->SetFont('');
	if($fetchQuiz["noParticipationPeriod"] == 1) {
		$pdf->Cell($w,$l,'keine zeitliche Begrenzung', 0, 1);
	} else {
		$pdf->Cell($w,$l,date("d. F Y H:i:s", $fetchQuiz["endtime"]), 0, 1);
	}
	
	$stmt = $dbh->prepare("select question.id, type_id, execution.singlechoice_multiplier from question inner join qunaire_qu on qunaire_qu.question_id = question.id 
						inner join questionnaire on questionnaire.id = qunaire_qu.questionnaire_id inner join qunaire_exec on qunaire_exec.questionnaire_id = questionnaire.id 
						inner join execution on qunaire_exec.execution_id = execution.id where execution.id = :execId");
	$stmt->bindParam(":execId", $_GET["execId"]);
	$stmt->execute();
	$fetchQuestions = $stmt->fetchAll(PDO::FETCH_ASSOC);
	$totalPoints = 0;
	for($i = 0; $i < count($fetchQuestions); $i++)
	{
	if($fetchQuestions[$i]["type_id"] == 1)
		$totalPoints+= (1*$fetchQuestions[0]["singlechoice_multiplier"]);
		else if($fetchQuestions[$i]["type_id"] == 2)
		{
			$stmt = $dbh->prepare("select answer_id as count from answer_question where question_id = :question_id");
			$stmt->bindParam(":question_id", $fetchQuestions[$i]["id"]);
			$stmt->execute();
			$totalPoints += $stmt->rowCount();
		}
	}
	
	$pdf->SetFont('helvetica','B',$fontSize);
	$pdf->Cell($w,$l,'Anzahl Fragen:');
	$pdf->SetFont('');
	$pdf->Cell($w,$l,count($fetchQuestions), 0, 1);
	
	$pdf->SetFont('helvetica','B',$fontSize);
	$pdf->Cell($w,$l,'Maximal mögliche Punktezahl:');
	$pdf->SetFont('');
	$pdf->Cell($w,$l,$totalPoints, 0, 1);
	
	
}


if($action == "getQuizTaskPaper")
{
	$pdf->Line(10, 66, 200, 66);
	
	$pdf->Ln(8);
	
	$stmt = $dbh->prepare("select id, text, type_id, picture_link from question inner join qunaire_qu on qunaire_qu.question_id = question.id where qunaire_qu.questionnaire_id = :quizId");
	$stmt->bindParam(":quizId", $fetchQuiz["qId"]);
	$stmt->execute();
	$fetchQuestions = $stmt->fetchAll(PDO::FETCH_ASSOC);
	
	for($i = 0; $i < count($fetchQuestions); $i++)
	{
		$fontSize = 10;
		$w = 0;
		$l = 6;
		
		$pdf->SetFont('helvetica','B',$fontSize);
		$pdf->SetFillColor(200,220,255);
		$pdf->MultiCell($w,$l,'Frage ' . ($i+1) . ': ' . $fetchQuestions[$i]["text"], 1, 'L', 1, 1, '', '', true);
		
		if($fetchQuestions[$i]["picture_link"] != null) {
			$pdf->Ln(1);
			$pdf->Image($fetchQuestions[$i]["picture_link"], '', '', '', '', 'JPG', '', '', true, 300, 'C', false, false, '', false, false, false);
			$pdf->Ln(getimagesize($fetchQuestions[$i]["picture_link"])[1]/14);
		}
		
		if($fetchQuestions[$i]["type_id"] == 2) {
			$pdf->SetFont($fontname,'B',$fontSize-1);
			$pdf->MultiCell($w,$l, "  ". getMultiplechoiceChar(-1) . "       " . getMultiplechoiceChar(0) . "      " . getMultiplechoiceChar(1). "      Antwortmöglichkeiten",  'LRT', 'L', 0, 0, '', '', true);
		}
		$pdf->SetFont('helvetica','B',$fontSize);
		
		$stmt = $dbh->prepare("select id, text from answer inner join answer_question on answer_question.answer_id = answer.id where answer_question.question_id = :qId order by `order`");
		$stmt->bindParam(":qId", $fetchQuestions[$i]["id"]);
		$stmt->execute();
		$fetchAnswers = $stmt->fetchAll(PDO::FETCH_ASSOC);
		
		
		for ($j = 0; $j < count($fetchAnswers); $j++)
		{
			$w = 15;
			
				
			$border = '';
			if($j == 0)
				$border .= 'T';
			if($j == count($fetchAnswers)-1)
				$border .= 'B';
			
			$pdf->SetFont('arial_unicode_ms');
			//Answer Text (Col 2)
			$w = 0;	
			
			if($fetchQuestions[$i]["type_id"] == 1) //singlechoice
			{
				$pdf->MultiCell($w,$l,"  O    " . $fetchAnswers[$j]["text"], $border . 'LR', 'L', 0, 1, '', '', true);
			} else if($fetchQuestions[$i]["type_id"] == 2) //multiplechoice
			{
				$pdf->MultiCell($w,$l,"  ☐    ☐    ☐" . "      " . $fetchAnswers[$j]["text"], $border . 'LR', 'L', 0, 1, '', '', true);
			}
			
			//$pdf->Cell($w,$l,"  O    " . $fetchAnswers[$j]["text"], $border . 'LR', 1, 'L');
			
			//$pdf->Ln(1);
		}
		$pdf->Ln(4);
	}
	
	// ---------------------------------------------------------
	
	//Close and output PDF document
	ob_end_clean();
	$pdf->Output($fetchQuiz["name"] . '_Fragen.pdf', 'I');
} else if($action == "getQuizTaskPaperWithMyAnswers")
{	
	$pdf->SetFont('helvetica','B',$fontSize);
	$pdf->Cell($w,$l,'Anzahl eigene Teilnahmen:');
	$pdf->SetFont('');
	$pdf->Cell($w,$l,$ownParticipationAmount, 0, 1);
	
	$stmt = $dbh->prepare("select firstname, lastname, email from user_data inner join user on user.id = user_data.user_id where user_id = :uId");
	$stmt->bindParam(":uId", $uId);
	$stmt->execute();
	$fetchUser = $stmt->fetch(PDO::FETCH_ASSOC);
	
	$pdf->SetFont('helvetica','B',$fontSize);
	$pdf->Cell($w,$l,'Teilnehmer:');
	$pdf->SetFont('');
	$pdf->Cell($w,$l,$fetchUser["firstname"] . " " . $fetchUser["lastname"] . " (" . $fetchUser["email"] . ") ", 0, 1);
	
	$choosedSession = null;
	$stmt = $dbh->prepare("select * from user_exec_session where user_id = :user_id and execution_id = :execId");
	$stmt->bindParam(":user_id", $uId);
	$stmt->bindParam(":execId", $_GET["execId"]);
	if (!$stmt->execute()) {
		header("Location: index.php?p=quiz&code=-14");
		exit();
	}
	$fetchSession = $stmt->fetchAll(PDO::FETCH_ASSOC);
	
	$tmpPoints = null;
	$fetchPoints = [0,0,0];
	
	for($j = 0; $j < count($fetchSession); $j ++) {
		$tmpPoints = getPoints($dbh, $fetchQuiz["qId"], $fetchSession[$j]["id"], 0);
		if ($j == 0 || $tmpPoints [0] >= $fetchPoints [0])
		{
			$fetchPoints = $tmpPoints;
			$choosedSession = $fetchSession[$j];
		}
	}
	
	$pdf->SetFont('helvetica','B',$fontSize);
	$pdf->Cell($w,$l,'Startzeitpunkt Teilnahme:');
	$pdf->SetFont('');
	$pdf->Cell($w,$l,date("d. F Y H:i:s", $choosedSession["starttime"]), 0, 1);
	
	$pdf->SetFont('helvetica','B',$fontSize);
	$pdf->Cell($w,$l,'Endzeitpunkt Teilnahme:');
	$pdf->SetFont('');
	$pdf->Cell($w,$l,date("d. F Y H:i:s", $choosedSession["endtime"]), 0, 1);
	
	$pdf->SetFont('helvetica','B',$fontSize);
	$pdf->Cell($w,$l,'Benötigte Zeit:');
	$pdf->SetFont('');
	$pdf->Cell($w,$l,gmdate("H:i:s", ($choosedSession["endtime"]-$choosedSession["starttime"])), 0, 1);
	
	$pdf->SetFont('helvetica','B',$fontSize);
	$pdf->Cell($w,$l,'Resultat beste Durchführung:');
	$pdf->SetFont('');
	$pdf->Cell($w,$l,$fetchPoints[0] . "/" . $fetchPoints[1] . " (" . $fetchPoints[2] . "%)", 0, 1);
	
	$pdf->Line(10, 102, 200, 102);
	
	$pdf->Ln(8);
	
	//Questions
	$stmt = $dbh->prepare("select picture_link, question.id as questionId, question.text as questionText, question.type_id, an_qu_user.question_order
			from question
			inner join qunaire_qu on qunaire_qu.question_id = question.id
			left outer join an_qu_user on an_qu_user.question_id = question.id and session_id = :session_id
			where qunaire_qu.questionnaire_id = :questionnaire_id group by question.id order by an_qu_user.question_order;");
	$stmt->bindParam(":questionnaire_id", $fetchQuiz["qId"]);
	$stmt->bindParam(":session_id", $choosedSession["id"]);
	if(!$stmt->execute())
	{
		header("Location: index.php?p=quiz&code=-26");
		exit;
	}
	$fetchQuestions = $stmt->fetchAll(PDO::FETCH_ASSOC);
	
	for($i = 0; $i < count($fetchQuestions); $i++)
	{
		$fontSize = 10;
		$w = 0;
		$l = 6;
		
		$pdf->SetFont('helvetica','B',$fontSize);
		$pdf->SetFillColor(200,220,255);
		$pdf->MultiCell($w,$l,'Frage ' . ($i+1) . ': ' . $fetchQuestions[$i]["questionText"], 1, 'L', 1, 1, '', '', true);
		
		if($fetchQuestions[$i]["picture_link"] != null) {
			$pdf->Ln(1);
			$pdf->Image($fetchQuestions[$i]["picture_link"], '', '', '', '', 'JPG', '', '', true, 300, 'C', false, false, 1, false, false, false);
			$pdf->Ln(getimagesize($fetchQuestions[$i]["picture_link"])[1]/14);
		}
		
		$pdf->SetFont('helvetica','B',$fontSize-3);
		$pdf->MultiCell($w,$l,"Richtige  Deine        Fragetext\nAntwort   Antwort" . $fetchAnswers[$j]["text"], 'LRT', 'L', 0, 1, '', '', true);
		
		$stmt = $dbh->prepare("select answer_question.answer_id, answer.text, answer_question.is_correct, (select selected from an_qu_user where answer_question.answer_id = an_qu_user.answer_id and session_id = :session_id) as selected
								from answer_question
								inner join answer on answer.id = answer_question.answer_id
								where answer_question.question_id = :question_id");
		$stmt->bindParam(":question_id", $fetchQuestions[$i]["questionId"]);
		$stmt->bindParam(":session_id", $choosedSession["id"]);
		if(!$stmt->execute())
		{
			header("Location: index.php?p=quiz&code=-26");
			exit;
		}
		$fetchAnswers = $stmt->fetchAll(PDO::FETCH_ASSOC);
		
		for($j = 0; $j < count($fetchAnswers); $j++)
		{
			$correctChar = "";
			$selectedChar = "";
			if($fetchQuestions[$i]["type_id"] == 1) //singlechoice
			{
				$correctChar = $fetchAnswers[$j]["is_correct"] == 1 ? '◉ ' : 'Ο';
			} else if($fetchQuestions[$i]["type_id"] == 2) //multiplechoice
			{
				$correctChar = getMultiplechoiceChar($fetchAnswers[$j]["is_correct"]);
			}
			
			if($fetchQuestions[$i]["type_id"] == 1) //singlechoice
			{
				$selectedChar = $fetchAnswers[$j]["selected"] == 1 && $fetchAnswers[$j]["selected"] != NULL ? '◉' : 'Ο';
			} else if($fetchQuestions[$i]["type_id"] == 2) //multiplechoice
			{
				if($fetchAnswers[$j]["selected"] != NULL)
					$selectedChar = getMultiplechoiceChar($fetchAnswers[$j]["selected"]);
				else
					$selectedChar = '-';
			}
			
			$border = '';
			if($j == 0)
				$border .= '';
			if($j == count($fetchAnswers)-1)
				$border .= 'B';
				
			$pdf->SetFont($fontname,'',$fontSize);
			$w = 0;
			$pdf->Cell($w,$l,"    " . $correctChar . "         " . $selectedChar . "        " . $fetchAnswers[$j]["text"], $border . 'LR', 1, 'L');
			
		}
		$pdf->Ln(4);
	}
	
	//Close and output PDF document
	ob_end_clean();
	$pdf->Output($fetchUser["lastname"] . "_" . $fetchUser["firstname"] . "_" . $fetchQuiz["name"] . ".pdf", 'I');
} else 
{
	header("Location: ?p=quiz&code=-36");
	exit;
}
