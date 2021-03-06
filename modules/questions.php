<?php
	
	if($_SESSION["role"]["user"])
	{
		if( ! $_SESSION["role"]["creator"])
		{
			header("Location: ?p=quiz&code=-1");
			exit;
		}
	}
	else 
	{
		header("Location: ?p=home&code=-20");
		exit;
	}
	
	$selectedLanguage = "all";
	$selectedTopic;
	
	$selectedCreator = "all";
	
	if(isset($_POST["owner"]))
	{
		$selectedCreator = $_POST["owner"];
	} 
	
	if(isset($_POST["language"]))
	{
		$selectedLanguage = $_POST["language"];
	}
	
	if($_POST["alreadyThere"] != "1")
	{
		$userId = $_SESSION["id"];
		$stmt = $dbh->prepare("select subject_id from `group` inner join user_group on `group`.id = user_group.group_id where user_group.user_id = $userId and `group`.subject_id is not null");
		$stmt->execute();
		$fetchUserInterestGroups = $stmt->fetchAll(PDO::FETCH_ASSOC);
		
		$numberOfUserInterests = count($fetchUserInterestGroups);
		$selectedTopic = array();
		
		for($i = 0; $i < $numberOfUserInterests; $i++)
		{
			array_push($selectedTopic, $fetchUserInterestGroups[$i]["subject_id"]);
		}
	} else 
	{
		$selectedTopic = "all";
	}
	
	
	if(isset($_POST["topic"]))
	{
		$selectedTopic = $_POST["topic"];
		if($selectedTopic == "null")
			$selectedTopic = null;
	}
	
?>
<div class="container theme-showcase">
	<div class="page-header">
		<h1><?php echo $lang["questionsHeadline"];?></h1>
	</div>
	<div class="panel panel-default">
		<div class="panel-body">
		
			<?php if($_SESSION['role']['creator'] == 1) {?>
			<button id="btnAddQuestion" class="btn btn-success" style="width: 260px; height: 3em; float: left; margin-bottom: 1em; margin-top: 8px" onclick="window.location='?p=createEditQuestion';"><?php echo $lang["createQuestion"]?> <span class="glyphicon glyphicon-plus"></span></button>			
	        <?php }?>
	        
	        
	        <div style="width: 100%; margin-bottom: 1em;">
				<div id="searchBoxDiv" class="control-group" style="width: 260px; margin-left:auto; margin-right:0;">
					<label class="control-label" for="searchbox">
						<b><?php echo $lang["search"]; ?></b>
						<input type="search" id="searchbox" class="form-control input-sm magnifyingGlassstyle" 
						style="width: 260px" placeholder="<?php echo $lang["enterSearchTerm"];?>">
					</label>
				</div>
			</div>
	        
	        
	        <div class="control-group">
	    		
	    		<a href="excelTemplate/<?php echo ($_SESSION["language"] == "ger") ? "Frage_Template_de.xlsx" : "Question_Template_en.xlsx"; ?>" download>
	    			<button class="btn btn-primary" style="width: 260px; height: 3em; float: left; margin-bottom: 1em;"><?php echo $lang["downloadExcelTemplate"]?> <span class="glyphicon glyphicon-download-alt"></span></button></a>
			</div>
		
			<form id="quizFilter" class="form-horizontal" action="?p=questions" method="POST" style="clear: both">
		    	<input type="hidden" name="alreadyThere" value="1" />
		    	
		    	<fieldset class="table-border">
					<legend class="table-border" style="margin-bottom: -1em"><?php echo $lang["filterOptions"];?></legend>
			    	
			    	<!-- Language FILTER -->
			        <div class="control-group">
			            <label class="control-label" for="language">
			                <?php echo $lang["quizLanguage"]?>
			            </label>
			            <div class="controls">
			                <select id="language" multiple class="form-control" name="language[]" onchange="sendData()">
			                	<?php 
			                	$stmt = $dbh->prepare("select id from question");
			                	$stmt->execute(); 
			                	$allQuestionsCount = $stmt->rowCount();
			                	
			                    $stmt = $dbh->prepare("select language from question group by language");
			                    $stmt->execute();
			                    $result = $stmt->fetchAll();
			                    
			                    for($i = 0; $i < count($result); $i++){
									$stmt = $dbh->prepare("select id from question where language = '" . $result[$i]["language"] . "'");
									$stmt->execute();
									$selected = (in_array($result[$i]["language"], $selectedLanguage)) ? 'selected="selected"' : '';
									echo "<option value=\"" . $result[$i]["language"] . "\"" . $selected . ">" . $result[$i]["language"] . " (" . $stmt->rowCount() . " " . $lang["questions"] . ")</option>";
			                    } ?>
			                </select>
			            </div>
			        </div>
			        
			        <!-- Topic FILTER -->
			        <div class="control-group">
			            <label class="control-label" for="topic">
			                <?php echo $lang["quizTopics"]?>
			            </label>
			            <div class="controls">
			                <select id="topic" multiple="multiple" class="form-control" name="topic[]" onchange="sendData()">
			                    <?php 
			                    $stmt = $dbh->prepare("select subject_id from question group by subject_id");
			                    $stmt->execute();
			                    $result = $stmt->fetchAll();
			                    
			                    for($i = 0; $i < count($result); $i++){
									if($result[$i]["subject_id"] == null)
									{
										$stmt = $dbh->prepare("select id from question where subject_id is null");
									}
									else 
										$stmt = $dbh->prepare("select id from question where subject_id = " . $result[$i]["subject_id"]);
									$stmt->execute();
									$rowCount = $stmt->rowCount();
									
									$stmt = $dbh->prepare("select name from subjects where id = " . $result[$i]["subject_id"]);
									$stmt->execute();
									$resultSubjectName = $stmt->fetchAll(PDO::FETCH_ASSOC);
									$selected = (in_array($result[$i]["subject_id"], $selectedTopic)) ? 'selected="selected"' : '';
									if($resultSubjectName[0]["name"] == null && $selectedTopic[0] == "null") {$selected = 'selected="selected"'; };
									$subjectName = ($resultSubjectName[0]["name"] == null) ? $lang["undefined"] : $resultSubjectName[0]["name"];
									$subjectId = ($result[$i]["subject_id"] == null) ? 'null' : $result[$i]["subject_id"];
									echo "<option value=\"" . $subjectId . "\" " . $selected . ">" . $subjectName . " (" . $rowCount . " " . $lang["questions"] . ")</option>";
			                    } ?>
			                </select>
			            </div>
			        </div>
			        
			        <!-- Owner FILTER -->
			        <div class="control-group">
			            <label class="control-label" for="owner">
			                <?php echo $lang["quizOwner"]?>
			            </label>
			            <div class="controls">
			                <select id="owner" multiple="multiple" class="form-control" name="owner[]" onchange="sendData()">
			                    <?php 
	
				                    $stmt = $dbh->prepare("select owner_id from question group by owner_id");
				                    $stmt->execute();
				                    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
			                    	
			                    	for($i = 0; $i < count($result); $i++){
										
										$stmt = $dbh->prepare("select firstname, lastname from user_data inner join user on user.id = user_data.user_id where user.id = " . $result[$i]["owner_id"]);
										$stmt->execute();
				                    	$fetchUser = $stmt->fetch(PDO::FETCH_ASSOC);
				                    	
				                    	$stmt = $dbh->prepare("select id from question where owner_id = :owner_id");
				                    	$stmt->bindParam(":owner_id", $result[$i]["owner_id"]);
				                    	$stmt-> execute();
				                    	$ownerRowCount = $stmt->rowCount();
				                    	
				                    	$selected = (in_array($result[$i]["owner_id"], $selectedCreator)) ? 'selected' : '';
										
										echo "<option value=\"" . $result[$i]["owner_id"] . "\" " . $selected . ">" . $fetchUser["firstname"] . " " . $fetchUser["lastname"] . " (" . $ownerRowCount . " " . $lang["questions"] . ")</option>";
			                    } ?>
			                </select>
			            </div>
			        </div>
		        </fieldset>
		        
			    <div class="listOfQuizzes">
			        <table class="tblListOfQuizzes" id="questions" style="width: 100%">
			            <thead>
			                <tr>
			                    <th>
			                        <?php echo $lang["questionQuestionText"]?>
			                    </th>
			                    <th></th>
			                    <th>
			                        <?php echo $lang["quizTableTopic"]?>
			                    </th>
			                    <th>
			                        <?php echo $lang["creator"]?>
			                    </th>
			                    <th>
			                        <?php echo $lang["questionAmountUsedAnswer"]?>
			                    </th>
			                    <th id="answerQuality" original-title="Qualit&auml;t der Antworten zu einzelnen Fragen (Durchschnittlich erreichte Punktezahl, -100% bis 100%)">
			                        <?php echo $lang["questionCalcCorrectAnswer"]?>
			                    </th>
			                    <th>
			                        <?php echo $lang["quizTableActions"]?>
			                    </th>
			                </tr>
			            </thead>
			            <tbody>
			                <?php
			                $whereStatement = "";
			                if($selectedLanguage != "all" || $selectedTopic != "all" || $selectedCreator != "all")
			                {
			                	$notFirst = false;
			                	$whereStatement = " where ";
			                	if($selectedLanguage != "all")
			                	{
			                		$whereStatement .= "(language = '$selectedLanguage[0]' ";
			                		
			                		$numberOfSelectedLanguages = count($selectedLanguage);
			                		for($i = 1; $i < $numberOfSelectedLanguages; $i++)
			                		{
			                			$whereStatement .= "or language = '$selectedLanguage[$i]' ";
			                		}
			                		
			                		$whereStatement .= ") ";
			                		$notFirst = true;
			                	}
			                	if($selectedTopic != "all")
			                	{
			                		if($notFirst)
			                		{
			                			$whereStatement .= " and ";
			                		}
			                		
			                		if($selectedTopic[0] == "null")
			                		{
			                			$whereStatement .= "(subject_id is null ";
			                		} else
			                		{
			                			$whereStatement .= "(subject_id = $selectedTopic[0] ";
			                		}
			                		
			                		$numberOfSelectetTopics = count($selectedTopic);
			                		for($i = 1; $i < $numberOfSelectetTopics; $i++)
			                		{
			                			if($selectedTopic[$i] == "null")
			                			{
			                				$whereStatement .= "or subject_id is null ";
			                			} else
			                			{
			                				$whereStatement .= "or subject_id = $selectedTopic[$i] ";
			                			}
			                		}
			                		 
			                		$whereStatement .= ") ";
			                		$notFirst = true;
			                		
			                	}
			                	if($selectedCreator != "all")
			                	{
			                		if($notFirst)
			                		{
			                			$whereStatement .= " and ";
			                		}
			                		
			                		$whereStatement .= "(owner_id = $selectedCreator[0] ";
			                		 
			                		$numberOfSelectetCreators = count($selectedCreator);
			                		for($i = 1; $i < $numberOfSelectetCreators; $i++)
			                		{
			                			$whereStatement .= "or owner_id = $selectedCreator[$i] ";
			                		}
			                		 
			                		$whereStatement .= ") ";
			                	}
			                }
			                
			                $stmt = $dbh->prepare("select question.id as q_id, question.*, subjects.id as s_id, subjects.*, user_data.firstname, user_data.lastname from question left outer join subjects on subjects.id = question.subject_id inner join user on user.id = question.owner_id inner join user_data on user_data.user_id = user.id" . $whereStatement);
			                if($selectedLanguage != "all"){$stmt->bindParam(":language", $selectedLanguage);}
			                if($selectedTopic != "all" && $selectedTopic != null){$stmt->bindParam(":subject_id", $selectedTopic);}
			                if($selectedCreator != "all"){$stmt->bindParam(":owner_id", $selectedCreator);}
			                $stmt->execute();
			                $resultArray = $stmt->fetchAll(PDO::FETCH_ASSOC);
			                
			                for($i = 0; $i < count($resultArray); $i++) {
							?>
			                    <tr class="entry" style="height: 75px" id="<?php echo "question_" . $resultArray[$i]["q_id"];?>">
			                        <?php 
			                        	$qType = "singlechoice";
			                        if($resultArray[$i]["type_id"] == 2)
			                        	$qType = "multiplechoice";
			                        ?>
			                        <td title="<?php echo htmlspecialchars($resultArray[$i]["text"]);?>">
			                            <img width="15" height="15" class="questionTypeInfo" original-title="<?php echo $qType;?>" style="margin-right: 5px; margin-bottom: 3px;" src="assets/icon_<?php echo $qType;?>.png"><a href="javascript:void(0)" onclick="openDialog(<?php echo $resultArray[$i]["q_id"];?>)"><?php echo substr(htmlspecialchars($resultArray[$i]["text"]), 0, 20);?></a>
			                        	<p id="arrowDown" style="float: right; margin-right: 1em; display: none">&#9660;</p>
			                        </td>
			                        <td>
			                            <?php echo htmlspecialchars($resultArray[$i]["text"]);?>
			                        </td>
			                        <td>
			                        	<?php echo ($resultArray[$i]["name"]==NULL) ? "Nicht zugeordnet" : $resultArray[$i]["name"];?>
			                        </td>
			                        <td>
			                        	<?php echo $resultArray[$i]["firstname"] . " " . $resultArray[$i]["lastname"];?>
			                        </td>
			                        <td>
			                        	<?php 
			                            $stmt = $dbh->prepare("select questionnaire_id, name from qunaire_qu inner join questionnaire on questionnaire.id = qunaire_qu.questionnaire_id where question_id = " . $resultArray[$i]["q_id"]);
			                            $stmt->execute();
			                            $fetchQuestionnaireName = $stmt->fetchAll(PDO::FETCH_ASSOC);
			                            $qunaireStr = "";
			                            for($j = 0; $j < count($fetchQuestionnaireName); $j++)
			                            {
			                            	if($j != 0)
			                            		$qunaireStr .= "<br />";
			                            	$qunaireStr .= $fetchQuestionnaireName[$j]["name"];
			                            }
			                            
			                            echo "<span class=\"amountUsed\" original-title=\"" . $qunaireStr . "\">" . $stmt->rowCount() . "</span>";
			                            ?>
			                        </td>
			                        <td>
			                        	<?php 
			                        	$stmt = $dbh->prepare("select * from an_qu_user inner join answer_question on answer_question.answer_id = an_qu_user.answer_id where an_qu_user.question_id = :qId");
			                        	$stmt->bindParam(":qId", $resultArray[$i]["q_id"]);
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
			                        		echo "(".$correctCounter."/".$totalCounter.") " . number_format(($correctCounter*100)/$totalCounter, 1) . "%";
			                        	else 
			                        		echo "-";
			                        	?>
			                        </td>
			                        <td>
				                        <button class="btn btn-secondary dropdown-toggle" type="button" id="dropdownMenuButton" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false"
											style="color: #373a3c; background-color: #fff; border-color: #ccc;"><?php echo $lang["action"];?></button>
										<div class="dropdown-menu" aria-labelledby="dropdownMenuButton" style="position: relative; margin-left: -120px">
									
			                        	<?php if($_SESSION['role']['admin'] == 1 || $resultArray[$i]["owner_id"] == $_SESSION["id"]) {?>
				                            <a class="dropdown-item" href="<?php echo "?p=createEditQuestion&mode=edit&id=" . $resultArray[$i]["q_id"];?>"><span class="glyphicon glyphicon-pencil"></span> <?php echo $lang["editQuestion"]?></a>
	                                		<a class="dropdown-item" onclick="delQuestion(<?php echo $resultArray[$i]["q_id"];?>)"><span class="glyphicon glyphicon-remove"></span> <?php echo $lang["deleteQuestion"]?></a>
			                        	<?php }?>
			                        	</div>
			                        </td>
			                    </tr>
			                <?php }?>
			            </tbody>
			        </table>
			    </div>
			</form>
		</div>
	</div>
</div>
<div id="dialog" title="Basic dialog"></div>


<script type="text/javascript">

	function delQuestion(id)
	{
		if(confirm("<?php echo $lang["deleteConfirmation"];?>"))
		{
		    $.ajax({
			      url: 'modules/actionHandler.php',
			      type: 'get',
			      data: 'action=delQuestion&userId='+<?php echo $_SESSION["id"]; ?>+'&questionId=' + id,
			      success: function(output) {
				      if(output == 'deleteQuestionOk')
				      {
					      $('#question_' + id).hide();
					      $('#questionActionResult').html("Frage erfolgreich entfernt.");
				      }
			      }, error: function()
			      {
			          alert("Deleting failed");
			      }
			   });
		}
	}

	function openDialog(qId)
	{
		$.ajax({
			url: 'modules/actionHandler.php',
			type: 'get',
			data: 'action=queryAnswers&questionId='+qId,
			dataType: 'json',
			success: function(output) {
				if(output[0] == 'getAnswersOk')
				{
					console.log(JSON.stringify(output));
					var dialogContent = "<div><b>"+output[1][0]+"</b><ol>";
					for(var i = 1; i < output[1].length; i++)
					{
						dialogContent += "<li>"+output[1][i]+"</li>";
					}
					dialogContent += "</ol></div>";
					
					
					$( "#dialog" ).html(dialogContent);
				} else {
					$( "#dialog" ).html("query failed 2");
				}
				$( "#dialog" ).dialog( "open" );
			}, error: function()
			{
				alert("query failed");
			}
		});
	}
	
    $(function() {
    	$('.deleteQuestion').tipsy({gravity: 'n'});
    	$('.editQuestion').tipsy({gravity: 'n'});
    	$('#answerQuality').tipsy({gravity: 'n'});
    	$('.amountUsed').tipsy({gravity: 'n', html: true});
    	$('.questionTypeInfo').tipsy({gravity: 'n'});

    	$( "#dialog" ).dialog({
    		autoOpen: false,
    		title: "Frage und Antworten",
			buttons: {
				"OK": function() {
					$( this ).dialog( "close" );
					$( "#dialog" ).html("");
				}
			}
		});
    	

        $('#questions').DataTable({
            sort: true,
            paginate: true,
            lengthChange: false,
            responsive: true,
            columns: [
            	{responsivePriority: 1},
                {visible: false},
                {responsivePriority: 5},
                {searchable: false, responsivePriority: 6},
                {searchable: false, responsivePriority: 7},
				{searchable: false, responsivePriority: 4},
				{searchable: false, sortable: false, responsivePriority: 3}
            ],
            dom: '<"toolbar">frtip',
            language: {
                zeroRecords: "Es sind keine Fragen dieser Art vorhanden",
                info: "Zeige von _START_ bis _END_ von insgesamt _TOTAL_ Fragen",
                infoEmpty: "Zeige von 0 bis 0 von insgesamt 0 Fragen",
                infoFiltered: "(von insgesamt _MAX_ Fragen)",
                search: ""
            }
        });        
        $('.dataTables_filter').css("display", "none");
    });

    $("#searchbox").on("keyup search input paste cut", function() {
    	$('#questions').dataTable().fnFilter(this.value);
    });
    
    function sendData() {
        $('#quizFilter').submit();
    }

    $(document).ready(function() {
        $('#language, #topic, #owner').multiselect({

        	buttonText: function(options, select) {
                if (options.length === 0) {
                    return '<?php echo $lang["all"]?>';
                }
                 else {
                     var labels = [];
                     options.each(function() {
                         if ($(this).attr('label') !== undefined) {
                             labels.push($(this).attr('label'));
                         }
                         else {
                             labels.push($(this).html());
                         }
                     });
                     return labels.join(', ') + '';
                 }
            }
        });
    });
</script>