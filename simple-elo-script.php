// Fetch all players with more than 4 matches played and last played within a year from script date
$players = array();
$sql = "SELECT * FROM players WHERE 
        matches_played > 4 AND 
        lastplayed >= DATE_SUB( NOW() , INTERVAL 1 YEAR )";
$res = mysql_query($sql) or die(mysql_error());

while($player = mysql_fetch_assoc($res)){
    $new_player = array();
    $new_player['user_id'] = $player['user_id'];
    $new_player['username'] = $player['username'];
    $new_player['elo'] = $player['site_rating'];
    $new_player['site_rating'] = 1200;
    $players[$player['username']] = $new_player;
}

// Calculate ELO's
$sql = "SELECT * FROM matches ORDER BY date ASC";
$res = mysql_query($sql) or die(mysql_error());
while($match = mysql_fetch_assoc($res)){
    $winner = $match['winner'];
    $loser = $match['player_one'];

    if(isset($players[$winner]) && isset($players[$loser])){
        if($match['player_one'] ==  $match['winner']){
            $loser = $match['player_two'];
        }
        $winner_elo = $players[$winner]['site_rating'];
        $loser_elo = $players[$loser]['site_rating'];

        // Explanation of ELO and Sonas
        // https://en.wikipedia.org/wiki/Elo_rating_system#Most_accurate_distribution_model
        $sonas = true;

        if($sonas){
            $k = 32;

            if ($winner_elo >= 2100)
            {
                $k = 24;
                if ($winner_elo >= 2400)
                {
                    $k = 16;
                }
            }
        }else{
            $k = 24;
        }


        $prob = 1/(1+pow(10,(($loser_elo-$winner_elo)/400)));
        $new_elo_winner = $k*(1-$prob);
        $new_elo_loser = -$k*(1-$prob);
        if(isset($players[$winner])){
            $players[$winner]['site_rating'] = $winner_elo + $new_elo_winner;
        }
        if(isset($players[$loser])){
            $players[$loser]['site_rating'] = $loser_elo + $new_elo_loser;
        }
    }
}

// Sort by new elo
usort($players, 'sort_compare');

// Update previous rankings (for historical purposes)
$sql = "UPDATE players SET site_rank_previous = site_rating";
$res = mysql_query($sql) or die(mysql_error());

// Clear players ratings and ranking
$sql = "UPDATE players SET site_rating = '1200', site_rank = '100000'";
$res = mysql_query($sql) or die(mysql_error());

// Update new elo's and rank
$count = 1;
foreach($players as $player){
    $sql = "UPDATE players SET site_rating = '".$player['site_rating']."', 
    site_rank = '".$count."' WHERE user_id = '".$player['user_id']."'";
    $res = mysql_query($sql) or die(mysql_error());
    $count++;
}

function sort_compare($a, $b){
	$param = 'site_rating';
	$sa = $a[$param];
	$sb = $b[$param];
	if ($sa == $sb){
		return 0;
	}
	return ($sa > $sb ? -1 : 1);
}
