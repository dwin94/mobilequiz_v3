<?php
	
	include_once 'errorCodeHandler.php';
	
	if($_SESSION["role"]["user"] != 1)
	{
		header("Location: ?p=home&code=-20");
		exit;
	}
?>
<script type="text/javascript">
	function askForRole()
	{
		$.ajax({
		      url: 'modules/profileSettings.php',
		      type: 'post',
		      data: 'action=changeRole&userId='+<?php echo $_SESSION["id"]; ?>+'&requestedRole='+document.getElementById("requestedRole").value,
		      success: function(output) 
		      {
		          console.log("requestedRole: ok " + document.getElementById("requestedRole").value + " " + output);
		          if(output == "ok")
		          {
			          $('#roleMsg').html("<span style=\"color: green;\">Erfolgreich</span>");
			          $('#saveRole').prop('disabled', true);
		          } else if(output == "requestExisting")
		          {
		        	  $('#roleMsg').html("<span style=\"color: red;\">Anfrage schon vorhanden</span>");
		          }	else {
		        	  $('#roleMsg').html("<span style=\"color: red;\">Fehler</span>");
		          }
		      }, error: function(output)
		      {
			      console.log(output);
		          alert("requestedRole: failed");
		      }
		   });
	}

	function groupClicked(id, name)
	{
		console.log("groupClicked " + id);
		var joinGroupContent = '<label><?php echo $lang["profileJoinGroupJoin"] . ": ";?> '+name+'</label><br />';
		joinGroupContent += '<input type="text" id="joinGroupToken" maxlength="10" placeholder="Token"><br />';
		joinGroupContent += '<input type="button" style="margin-top: 5px;" class="btn" value="<?php echo $lang["profileJoinGroupJoin"];?>" onclick="joinTheGroupClicked('+id+')">';
		$('#groupMemberStatus').html(joinGroupContent);
	}

	function joinTheGroupClicked(id)
	{
		var token = $('#joinGroupToken').val();
		console.log("joinTheGroupClicked " + id + " " + token);
		$.ajax({
			url: 'modules/profileSettings.php',
			type: 'post',
			data: {
				action: 'joinGroup',
				groupToJoin: id,
				token: token
			},
			dataType: 'json',
			success: function(output) 
			{
				if(output[0] == "ok")
				{
					$( "#joinGroup li").each(function(index, elem) {
						$(elem).removeClass('joinGroupSelected');
					});
					$('#' + output[1]).addClass('joinGroupSelected');
					$('#groupMemberStatus').html('<span style="color: green;"><?php echo $lang["profileJoinGroupSuccess"];?></span>');
				}
			    else if(output[0] == "failed")
			    {
				    console.log(output);
			    	$('#groupMemberStatus').html('<span style="color: red;"><?php echo $lang["profileJoinGroupFailed"];?></span>');
				}
			}, error: function(a, b, c)
			{
				console.log(a);
				console.log(b);
				console.log(c);
		        alert("joinGroup: failed");
		    }
		});
	}

	$(document).ready(function() {
	    $('#topic').multiselect({

	    	buttonText: function(options, select) {
	            if (options.length === 0) {
	                return '<?php echo $lang["pleaseChoose"]?>';
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
	
	$(function() {

		$( "#profileRoleContent" ).hide();
		$( "#profileContent" ).hide();
		$( "#profileJoinGroupContent" ).hide();

		var mainContent = false;
		var roleContent = false;
		var joinGroupContent = false;
		
		$("#profileRoleHeading").click(function() {
			$( "#profileRoleContent" ).toggle("slow", false);
			roleContent = !roleContent;
			if(!roleContent)
				$('#arrowRole').html('&#9654;');
			else
				$('#arrowRole').html('&#9660;');
		});
		
		$("#profileHeading").click(function() {
			$( "#profileContent" ).toggle("slow", false);
			mainContent = !mainContent;
			if(!mainContent)
				$('#arrowMain').html('&#9654;');
			else
				$('#arrowMain').html('&#9660;');
		});

		$("#profileJoinGroupHeading").click(function() {
			$( "#profileJoinGroupContent" ).toggle("slow", false);
			joinGroupContent = !joinGroupContent;
			if(!joinGroupContent)
				$('#arrowJoinGroup').html('&#9654;');
			else
				$('#arrowJoinGroup').html('&#9660;');
		});
	});
</script>
<?php
	
	$errorCode = new mobileError("", "red");
	if(isset($_GET["code"]))
	{
		if(isset($_GET["subcode"]))
		{
			$errorCode = handleProfileError($_GET["code"], $_GET["subcode"]);
		} else
		{
			$errorCode = handleProfileError($_GET["code"], NULL);
		}
	}
	
	$stmt = $dbh->prepare("select * from user inner join user_data on id = user_id where id = :id");
	$stmt->bindParam(":id", $_SESSION["id"]);
	if(!$stmt->execute())
	{
		header("Location: ?p=quiz&code=-2");
		exit;
	}
	$fetchUser = $stmt->fetch(PDO::FETCH_ASSOC);
?>
<div class="container theme-showcase">
	<div class="page-header" >
		<h1><?php echo $lang["profileHeadline"];?></h1>
	</div>
	<p><?php echo $lang["profileWelcome"]?></p>
	<p style="color:<?php echo $errorCode->getColor(); ?>;"><?php echo $errorCode->getText();?></p>
	<div class="panel panel-default" >
		<div class="panel-heading" id="profileHeading">
			<span id="arrowMain" style="float:left; margin-right: 7px;">&#9654;</span>  <h3 class="panel-title"><?php echo $lang["pwRecoverySectionHeadline"]?></h3>
		</div>
		<form method="POST" action="?p=profileSettings&action=saveProfileData" >
			<div class="panel-body" id="profileContent">
			    <div class="control-group">
			    
			        <label class="control-label" for="topic">
			            <?php echo $lang["fieldOfInterest"]?> *
			        </label>
			        <div class="controls" style="margin-bottom: 1.5em;">
		                <select id="topic" multiple="multiple" class="form-control" name="topic[]" required>
		                    <?php 
		                    function in_array_r($needle, $haystack, $strict = false) {
		                    	foreach ($haystack as $item) {
		                    		if (($strict ? $item === $needle : $item == $needle) || (is_array($item) && in_array_r($needle, $item, $strict))) {
		                    			return true;
		                    		}
		                    	}
		                    
		                    	return false;
		                    }
		                    
		                    
		                    $stmt = $dbh->prepare("select subject_id from question group by subject_id");
		                    $stmt->execute();
		                    $result = $stmt->fetchAll();
		                    
		                    $userId = $_SESSION["id"];
		                    $stmt = $dbh->prepare("select subject_id from `group` inner join user_group on group.id = user_group.group_id where user_group.user_id = $userId and group.subject_id is not null");
		                    $stmt->execute();
		                    $userInterestGroups = $stmt->fetchAll(PDO::FETCH_ASSOC);
		                    
		                    for($i = 0; $i < count($result); $i++){
								if($result[$i]["subject_id"] == null)
								{
									continue;
								}
								
								$stmt = $dbh->prepare("select name from subjects where id = " . $result[$i]["subject_id"]);
								$stmt->execute();
								$resultSubjectName = $stmt->fetchAll(PDO::FETCH_ASSOC);
															
								$selected = (in_array_r($result[$i]["subject_id"], $userInterestGroups)) ? "selected" : "";
								
								echo "<option value=\"" . $result[$i]["subject_id"] . "\" " . $selected . ">" . $resultSubjectName[0]["name"] . "</option>";
		                    } ?>
		                </select>
					</div>
			    
			    
			        <label class="control-label" for="email">
			            <?php echo $lang["email"]?>*
			        </label>
			        <div class="controls">
			        	<input type="email" class="form-control text-input" value="<?php echo $fetchUser["email"];?>" name="email" placeholder="<?php echo $lang["your"] . " " .$lang["email"]?>" maxlength="100" />
			        </div>
			    </div>
		        <div class="control-group">
		            <label class="control-label" for="newPassword">
		                <?php echo $lang["newPassword"]?>
		            </label>
		            <div class="controls">
		                <input type="password" class="form-control text-input" id="newPassword" name="newPassword" placeholder="<?php echo $lang["newPassword"]?>" maxlength="100" />
		            </div>
		        </div>
		        <div class="control-group">
		            <label class="control-label" for="confirmNewPassword">
		                <?php echo $lang["confirmNewPassword"]?>
		            </label>
		            <div class="controls">
		                <input type="password" class="form-control text-input" id="confirmNewPassword" name="confirmNewPassword" placeholder="<?php echo $lang["confirmNewPassword"]?>" maxlength="100" />
		            </div>
		        </div>
			    <div style="height: 20px;"></div>
			    <div class="control-group">
			        <label class="control-label" for="firstname">
			            <?php echo $lang["firstname"]?>*
			        </label>
			        <div class="controls">
			            <input type="text" class="form-control text-input" value="<?php echo $fetchUser["firstname"];?>" name="firstname" placeholder="<?php echo $lang["your"] . " " .$lang["firstname"]?>" maxlength="40" value=""/>
			        </div>
			    </div>
			    <div class="control-group">
			        <label class="control-label" for="lastname">
			            <?php echo $lang["lastname"]?>*
			        </label>
			        <div class="controls">
			            <input type="text" class="form-control text-input" value="<?php echo $fetchUser["lastname"];?>" name="lastname" placeholder="<?php echo $lang["your"] . " " .$lang["lastname"]?>" maxlength="40" value="" />
			        </div>
			    </div>
			    <div class="control-group">
			        <label class="control-label" for="nickname">
			            <?php echo $lang["nickname"]?>
			        </label>
			        <div class="controls">
			            <input type="text" class="form-control text-input" value="<?php echo $fetchUser["nickname"];?>" name="nickname" placeholder="<?php echo $lang["your"] . " " .$lang["nickname"]?>" maxlength="40" value="" />
			        </div>
			    </div>
			    <div style="height: 20px;"></div>
			    <div class="control-group">
			        <label class="control-label" for="street">
			            <?php echo $lang["street"]?>
			        </label>
			        <div class="controls">
			            <input type="text" class="form-control text-input"  value="<?php echo $fetchUser["street"];?>" name="street" placeholder="<?php echo $lang["your"] . " " .$lang["street"]?>" maxlength="255" value="" />
			        </div>
			    </div>
			    <div class="control-group">
			        <label class="control-label" for="plz">
			            <?php echo $lang["zipcode"]?>
			        </label>
			        <div class="controls">
			            <input type="number" class="form-control text-input" value="<?php echo $fetchUser["plz"];?>" name="plz" placeholder="<?php echo $lang["your"] . " " .$lang["zipcode"]?>" maxlength="25" value="" />
			        </div>
			    </div>
			    <div class="control-group">
			        <label class="control-label" for="city">
			            <?php echo $lang["country"]?>
			        </label>
			        <div class="controls">
			            <input type="text" class="form-control text-input" value="<?php echo $fetchUser["city"];?>" name="city" placeholder="<?php echo $lang["your"] . " " .$lang["country"]?>" maxlength="25" value="" />
			        </div>
			    </div>
			    <div class="control-group">
			        <label class="control-label" for="telephone">
			            <?php echo $lang["telnumber"]?>
			        </label>
			        <div class="controls">
			            <input type="tel" class="form-control text-input" value="<?php echo $fetchUser["tel"];?>" name="telephone" placeholder="<?php echo $lang["your"] . " " .$lang["telnumber"]?>" maxlength="25" value="" />
			        </div>
			    </div>
			    <div style="height: 20px;"></div>
		        <div class="control-group">
		            <label class="control-label" for="oldPass">
		                <?php echo $lang["oldPassword"]?>
		            </label>
		            <div class="controls">
		                <input type="password" class="form-control text-input" id="oldPass" name="oldPass" placeholder="<?php echo $lang["oldPassword"]?>" maxlength="100" />
		            </div>
		        </div>
			    <div style="height: 20px;"></div>
		        <div style="text-align: left; float: left">
			        <input type="submit" class="btn" name="save" id="save" value="<?php echo $lang["buttonSave"]?>" />
			    </div>
			</div>
		</form>
	</div>
	<?php 
	$stmt = $dbh->prepare("select group_id from user where id = :uId");
	$stmt->bindParam(":uId", $_SESSION["id"]);
	$stmt->execute();
	$fetchUserGroupId = $stmt->fetch(PDO::FETCH_ASSOC);
	
	$stmt = $dbh->prepare("select * from `group`");
	$stmt->execute();
	$fetchGroups = $stmt->fetchAll(PDO::FETCH_ASSOC);
	$groupNameIn = "";
	for($i = 0; $i < count($fetchGroups); $i++)
	{
		if($fetchGroups[$i]["id"] == $fetchUserGroupId["group_id"])
			$groupNameIn = $fetchGroups[$i]["name"];
	}
	?>
	<div class="panel panel-default">
		<div class="panel-heading" id="profileJoinGroupHeading">
			<span id="arrowJoinGroup" style="float:left; margin-right: 7px;">&#9654;</span>  <h3 class="panel-title"><?php echo $lang["profileJoinGroupHeading"] . " (" . $groupNameIn . ")";?></h3>
		</div>
		<div class="panel-body" id="profileJoinGroupContent" >
		    <div class="control-group">
		        <label class="control-label" for="joinGroup">
		            <?php echo $lang["profileJoinGroupContent"]?>
		        </label><br />
		        <div style="min-height: 75px; max-height: 200px; overflow-y: scroll; width: 250px; float: left; margin-right: 20px;">
					<ul id="joinGroup" class="joinGroup">
						<?php 
						for($i = 0; $i < count($fetchGroups); $i++)
						{
						?>
							<li class="ui-state-default groupName<?php if($fetchGroups[$i]["id"] == $fetchUserGroupId["group_id"]) {echo " joinGroupSelected";}?>" style="cursor: pointer;" onclick="groupClicked(<?php echo $fetchGroups[$i]["id"] . ", '" . $fetchGroups[$i]["name"];?>')" original-title="<?php echo $fetchGroups[$i]["name"];?>" id="<?php echo $fetchGroups[$i]["id"];?>"><?php echo strlen($fetchGroups[$i]["name"]) > 19 ? substr($fetchGroups[$i]["name"], 0, 19) . "..." : $fetchGroups[$i]["name"];?></li>
						<?php }?>
					</ul>
				</div>
				<div>
					<span id="groupMemberStatus">
					<?php 
					if($groupNameIn != "")
						echo $lang["profileJoinGroupIn"] . ": " . $groupNameIn;
					else
						echo $lang["profileJoinGroupNotAMember"];
					?>
					</span>
				</div>
		    </div>
		    <div style="height: 20px;"></div>
		</div>
	</div>
	<div class="panel panel-default">
		<div class="panel-heading" id="profileRoleHeading">
			<span id="arrowRole" style="float:left; margin-right: 7px;">&#9654;</span>  <h3 class="panel-title"><?php echo $lang["askForRole"]?></h3>
		</div>
		<div class="panel-body" id="profileRoleContent" >
		    <div class="control-group">
		        <label class="control-label" for="askForRole">
		            <?php echo $lang["role"]?>
		        </label>
		        <div class="controls" id="askForRole">
		        <select id="requestedRole">
		            <option value="creator"> <?php echo $lang["roleCreator"]?>
		            <option value="manager"> <?php echo $lang["roleManager"]?>
		            <option value="admin"> <?php echo $lang["roleAdmin"]?>
		        </select>
		        </div>
		        <p id="roleMsg" style="height: 20px;"> </p>
			    <div style="text-align: right; float: left;">
			        <input type="submit" class="btn" name="saveRole" id="saveRole" value="<?php echo $lang["buttonSave"]?>" <?php echo "onclick=\"askForRole()\""?>/>
			    </div>
		    </div>
		    <div style="height: 20px;"></div>
		</div>
	</div>
</div>