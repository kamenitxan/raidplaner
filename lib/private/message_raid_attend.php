<?php

    function removeOverbooked($aRaidId, $aSlotRoles, $aSlotCount)
    {
        $Connector = Connector::getInstance();
    
        $Roles = explode(":", $aSlotRoles);
        $RoleCounts = array_combine($Roles, explode(":", $aSlotCount));
        
        foreach ($Roles as $Role)
        { 
            $MaxSlotCount = intval($RoleCounts[$Role]);
        
            // Check constraints for auto-attend
            // This fixes a rare race condition where two (or more) players attend
            // the last available slot at the same time.
            
            $AttendenceQuery = $Connector->prepare("SELECT AttendanceId ".
                                                   "FROM `".RP_TABLE_PREFIX."Attendance` ".
                                                   "WHERE RaidId = :RaidId AND Status = \"ok\" AND Role = :RoleId ".
                                                   "ORDER BY AttendanceId DESC LIMIT :MaxCount" );
        
            $AttendenceQuery->bindValue(":RaidId", intval($aRaidId), PDO::PARAM_INT);
            $AttendenceQuery->bindValue(":RoleId", $Role, PDO::PARAM_STR);
            $AttendenceQuery->bindValue(":MaxCount", intval($MaxSlotCount), PDO::PARAM_INT);
        
            $LastAttend = $AttendenceQuery->fetchFirst();
        
            if ( $AttendenceQuery->getAffectedRows() == $MaxSlotCount )
            {
                // Fix the overhead
        
                $FixQuery = $Connector->prepare("UPDATE `".RP_TABLE_PREFIX."Attendance` SET Status = \"available\" ".
                                                "WHERE RaidId = :RaidId AND Status = \"ok\" AND Role = :RoleId ".
                                                "AND AttendanceId > :FirstId" );
        
                $FixQuery->bindValue(":RaidId", intval($aRaidId), PDO::PARAM_INT);
                $FixQuery->bindValue(":RoleId", $Role, PDO::PARAM_STR);
                $FixQuery->bindValue(":FirstId", intval($LastAttend["AttendanceId"]), PDO::PARAM_INT);
        
                $FixQuery->execute();
            }
        }
    }
    
    // -------------------------------------------------------------------------

    function msgRaidAttend( $aRequest )
    {
        if (validUser())
        {
            global $gGame;
            
            loadGameSettings();
            $Connector = Connector::getInstance();
    
            $AttendanceIdx = intval( $aRequest["attendanceIndex"] );
            $RaidId = intval( $aRequest["raidId"] );
            $UserId = intval( UserProxy::getInstance()->UserId );
    
            // check user/character match
    
            $ChangeAllowed = true;
            $RaidInfo = Array();
            $Role = "";
            $Class = "";
    
            // Check if locked
    
            $LockCheckQuery = $Connector->prepare("SELECT Stage, Mode, SlotRoles, SlotCount FROM `".RP_TABLE_PREFIX."Raid` WHERE RaidId = :RaidId LIMIT 1");
            $LockCheckQuery->bindValue(":RaidId", intval($RaidId), PDO::PARAM_INT);
    
            $RaidInfo = $LockCheckQuery->fetchFirst();
    
            if ( $RaidInfo == null )
                return; // ### return, locked ###
    
            $ChangeAllowed = $RaidInfo["Stage"] == "open";
    
            if ( $ChangeAllowed )
            {
                // Check if character matches user
    
                if ( $AttendanceIdx > 0)
                {
                    $CheckQuery = $Connector->prepare("SELECT UserId, Class, Role1 FROM `".RP_TABLE_PREFIX."Character` WHERE CharacterId = :CharacterId AND Game = :Game LIMIT 1");
                    
                    $CheckQuery->bindValue(":CharacterId", intval($AttendanceIdx), PDO::PARAM_INT);
                    $CheckQuery->bindValue(":Game", intval($gGame["GameId"]), PDO::PARAM_INT);
    
                    $CharacterInfo = $CheckQuery->fetchFirst();
    
                    if ($CharacterInfo != null)
                    {
                        $ChangeAllowed &= ($CharacterInfo["UserId"] == $UserId );
                        $Role = $CharacterInfo["Role1"];
                        $Classes = explode(":",$CharacterInfo["Class"]);
                        $Class = $Classes[0];
                    }
                    else
                    {
                        $ChangeAllowed = false;
                    }
                }
    
                // update/insert new attendance data
    
                if ( $ChangeAllowed )
                {
                    $CheckQuery = $Connector->prepare("SELECT UserId FROM `".RP_TABLE_PREFIX."Attendance` WHERE UserId = :UserId AND RaidId = :RaidId LIMIT 1");
                    $CheckQuery->bindValue(":UserId", intval($UserId), PDO::PARAM_INT);
                    $CheckQuery->bindValue(":RaidId", intval($RaidId), PDO::PARAM_INT);
                    $CheckQuery->execute();
    
                    $AttendQuery = null;
                    $ChangeComment = isset($aRequest["comment"]) && ($aRequest["comment"] != "");
    
                    if ( $CheckQuery->getAffectedRows() > 0 )
                    {
                        if ( $ChangeComment  )
                        {
                            $AttendQuery = $Connector->prepare("UPDATE `".RP_TABLE_PREFIX."Attendance` SET ".
                                "CharacterId = :CharacterId, Status = :Status, Class = :Class, Role = :Role, Comment = :Comment, LastUpdate = FROM_UNIXTIME(:Timestamp) ".
                                "WHERE RaidId = :RaidId AND UserId = :UserId LIMIT 1" );
                        }
                        else
                        {
                            $AttendQuery = $Connector->prepare("UPDATE `".RP_TABLE_PREFIX."Attendance` SET ".
                                "CharacterId = :CharacterId, Status = :Status, Class = :Class, Role = :Role, LastUpdate = FROM_UNIXTIME(:Timestamp) ".
                                "WHERE RaidId = :RaidId AND UserId = :UserId LIMIT 1" );
    
                        }
                    }
                    else
                    {
                        if ( $ChangeComment )
                        {
                            $AttendQuery = $Connector->prepare("INSERT INTO `".RP_TABLE_PREFIX."Attendance` ( CharacterId, UserId, RaidId, Status, Class, Role, Comment, LastUpdate ) ".
                                "VALUES ( :CharacterId, :UserId, :RaidId, :Status, :Class, :Role, :Comment, FROM_UNIXTIME(:Timestamp) )" );
                        }
                        else
                        {
                            $AttendQuery = $Connector->prepare("INSERT INTO `".RP_TABLE_PREFIX."Attendance` ( CharacterId, UserId, RaidId, Status, Class, Role, Comment, LastUpdate) ".
                                "VALUES ( :CharacterId, :UserId, :RaidId, :Status, :Class, :Role, '', FROM_UNIXTIME(:Timestamp) )" );
                        }
                    }
    
                    // Define the status and id to set
    
                    if ( $AttendanceIdx == -1 )
                    {
                        $Status = "unavailable";
                        $CharacterId = intval( $aRequest["fallback"] );
                    }
                    else
    
                    {
                        $CharacterId = $AttendanceIdx;
    
                        switch ( $RaidInfo["Mode"] )
                        {
                        case "all":
                        case "attend":
                            $Status = "ok";
                            break;
    
                        default:
                        case "manual":
                        case "overbook":
                            $Status = "available";
                            break;
                        }
                    }
    
                    // Add comment when setting absent status
    
                    if ( $ChangeComment )
                    {
                        $Comment = requestToXML( $aRequest["comment"], ENT_COMPAT, "UTF-8" );
                        $AttendQuery->bindValue(":Comment", $Comment, PDO::PARAM_STR);
                    }
    
                    $AttendQuery->bindValue(":CharacterId", intval($CharacterId), PDO::PARAM_INT);
                    $AttendQuery->bindValue(":RaidId",      intval($RaidId),      PDO::PARAM_INT);
                    $AttendQuery->bindValue(":UserId",      intval($UserId),      PDO::PARAM_INT);
                    $AttendQuery->bindValue(":Status",      $Status,              PDO::PARAM_STR);
                    $AttendQuery->bindValue(":Role",        $Role,                PDO::PARAM_STR);
                    $AttendQuery->bindValue(":Class",       $Class,               PDO::PARAM_STR);
                    $AttendQuery->bindValue(":Timestamp",   time(),               PDO::PARAM_INT);
    
                    if ( $AttendQuery->execute() &&
                         ($Role != "") &&
                         ($RaidInfo["Mode"] == "attend") &&
                         ($Status == "ok") )
                    {
                        removeOverbooked($RaidId, $RaidInfo["SlotRoles"], $RaidInfo["SlotCount"]);
                    }
                }
                else
                {
                    $Out = Out::getInstance();
                    $Out->pushError(L("AccessDenied"));
                }
            }
            else
            {
                $Out = Out::getInstance();
                $Out->pushError(L("RaidLocked"));
            }
    
            // reload calendar
    
            $RaidQuery = $Connector->prepare("SELECT Start FROM `".RP_TABLE_PREFIX."Raid` WHERE RaidId = :RaidId LIMIT 1");
            $RaidQuery->bindValue(":RaidId", intval( $RaidId), PDO::PARAM_INT);
    
            $RaidData = $RaidQuery->fetchFirst();
            
            $Session = Session::get();
    
            $ShowMonth = ( isset($Session["Calendar"]) && isset($Session["Calendar"]["month"]) ) ? $Session["Calendar"]["month"] : intval( substr( $RaidData["Start"], 5, 2 ) );
            $ShowYear  = ( isset($Session["Calendar"]) && isset($Session["Calendar"]["year"]) )  ? $Session["Calendar"]["year"]  : intval( substr( $RaidData["Start"], 0, 4 ) );
    
            msgQueryCalendar( prepareCalRequest( $ShowMonth, $ShowYear ) );
        }
        else
        {
            $Out = Out::getInstance();
            $Out->pushError(L("AccessDenied"));
        }
    }

?>