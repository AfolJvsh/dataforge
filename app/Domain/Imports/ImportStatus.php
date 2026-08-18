<?php
namespace App\Domain\Imports;
enum ImportStatus:string {case Draft='draft';case Uploading='uploading';case Analyzing='analyzing';case Ready='ready';case Queued='queued';case Processing='processing';case Completed='completed';case CompletedWithErrors='completed_with_errors';case Failed='failed';case Cancelled='cancelled';}
