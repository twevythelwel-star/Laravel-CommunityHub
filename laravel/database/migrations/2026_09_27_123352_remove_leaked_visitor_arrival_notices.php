<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/*
 | Visitor arrival alerts were posted through the in-app channel, which writes a
 | community notice with no audience: every check-in (visitor, gate, vehicle)
 | was shown to every resident. The alert now goes to the host alone; this
 | removes the notices already posted.
 |
 | Matched on exactly what that code wrote (title prefix, no author, the system
 | author name), so a notice an administrator wrote is never touched. There is
 | no down(): restoring them would re-publish the leak.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('notifications')
            ->where('title', 'like', 'Visitor Arrived: %')
            ->whereNull('author_id')
            ->where('author_name', 'Community Hub Management')
            ->delete();
    }

    public function down(): void
    {
        //
    }
};
