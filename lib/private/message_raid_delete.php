<?php

    function msgRaidDelete( $aRequest )
    {
        if ( validRaidlead() )
        {
            $Connector = Connector::getInstance();
            
            // Call plugins
            
            $RaidId = intval($aRequest["id"]);        
            PluginRegistry::ForEachPlugin(function($PluginInstance) use ($RaidId)
            {
                $PluginInstance->onRaidRemove($RaidId); 
            });
    
            // Delete raid
    
            $Connector->beginTransaction();
    
            $DeleteRaidQuery = $Connector->prepare("DELETE FROM `".RP_TABLE_PREFIX."Raid` WHERE RaidId = :RaidId LIMIT 1" );
            $DeleteRaidQuery->bindValue(":RaidId", intval($aRequest["id"]), PDO::PARAM_INT);
    
            if (!$DeleteRaidQuery->execute())
            {
                $Connector->rollBack();
                return; // ### return, error ###
            }
    
            // Delete attendance
    
            $DeleteAttendanceQuery = $Connector->prepare("DELETE FROM `".RP_TABLE_PREFIX."Attendance` WHERE RaidId = :RaidId" );
            $DeleteAttendanceQuery->bindValue(":RaidId", intval($aRequest["id"]), PDO::PARAM_INT);
    
            if (!$DeleteAttendanceQuery->execute())
            {
                $Connector->rollBack();
                return; // ### return, error ###
            }
    
            $Connector->commit();
    
            $Session = Session::get();
            
            $ShowMonth = ( isset($Session["Calendar"]) && isset($Session["Calendar"]["month"]) ) ? $Session["Calendar"]["month"] : $aRequest["month"];
            $ShowYear  = ( isset($Session["Calendar"]) && isset($Session["Calendar"]["year"]) )  ? $Session["Calendar"]["year"]  : $aRequest["year"];
    
            msgQueryCalendar( prepareCalRequest( $ShowMonth, $ShowYear ) );
        }
        else
        {
            $Out = Out::getInstance();
            $Out->pushError(L("AccessDenied"));
        }
    }

?>