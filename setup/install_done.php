<?php
    define( "LOCALE_SETUP", true );
    require_once(dirname(__FILE__)."/../lib/private/locale.php");
?>
<?php readfile("layout/header.html"); ?>

<script type="text/javascript">
    $(document).ready( function() {
        //$(".button_back").click( function() { open("install_bindings.php"); });
        $(".button_next").click( function() { open("index.php"); });
        
        $("#remove").click( function() {        
            if ( confirm("<?php echo L("RemoveAndLaunch"); ?>?") )
            {        
                $.ajax({
                    type     : "POST",
                    url      : "../lib/private/clear_setup.php",
                    dataType : "json",
                    async    : true,
                    data     : null,
                    error    : function(aXHR, aStatus, aError) { alert(L("Error") + ":\n\n" + aError); },
                    success  : function(aXHR) { 
                        if ((aXHR.error != null) && (aXHR.error.length > 0))
                        {
                            var ErrorString = "";
                            for (var i=0; i<aXHR.error.length; ++i)
                            {
                                ErrorString += aXHR.error[i]+"\n";
                            }
                            
                            alert(L("Error") + ":\n\n" + ErrorString);
                        }
                        else
                        {
                            open("../index.php");
                        }
                    }
                });
            }
        });
    });
</script>

<h2><?php echo L("SecurityWarning"); ?></h2>
<?php

    echo L("RaidplanerSetupDone")."<br/>";
    echo L("DeleteSetupFolder")."<br/>";
?>

<button id="remove" class="clear"><?php echo L("RemoveAndLaunch"); ?></button>
<br/>

<?php
    echo L("ThankYou")."<br/>";
    echo L("VisitBugtracker");
    echo "<a href=\"https://github.com/arnecls/raidplaner/issues\">GitHub</a>."
?>

</div>
<div class="bottom_navigation">
    <!--<div class="button_back" style="background-image: url(layout/bindings_white.png)"><?php echo L("Back"); ?></div>-->
    <div class="button_next" style="background-image: url(layout/install_white.png)"><?php echo L("Continue"); ?></div>

<?php readfile("layout/footer.html"); ?>