<?php
	session_name("mugraid");
    session_start();
	
	header("Content-type: text/javascript");
?>

function loadDefaultCalendar()
{
	<?php if ( isset( $_SESSION["Calendar"] ) ) { ?>
	
	loadCalendar( <?php echo $_SESSION["Calendar"]["month"] ?>, <?php echo $_SESSION["Calendar"]["year"] ?>, 0 );

	<?php } else { ?>
	
	loadCalendarForToday();
	
	<?php } ?>
}