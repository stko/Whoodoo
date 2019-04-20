<?php

require_once  'JobsHandler.php';




if (!debug_backtrace()) {
	// do useful stuff
	$jh=JobsHandler::Instance();
	$jh->updateModelState(2,1);
}
?>
