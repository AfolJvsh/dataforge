<?php
namespace App\Domain\Imports;
enum ChunkStatus:string {case Pending='pending';case Processing='processing';case Completed='completed';case Failed='failed';case Cancelled='cancelled';}
