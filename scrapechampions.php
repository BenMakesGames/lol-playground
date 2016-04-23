<?php
echo '<pre>';

echo 'Scraping Champion names...<br />';

$championListHTML = file_get_contents('http://leagueoflegends.wikia.com/wiki/Category:Released_champion');

// this is so crazy, but...

// grab only the HTML content between these two ID strings
$start = strpos($championListHTML, 'id="mw-pages"');
$end = strpos($championListHTML, 'id="articleCategories"');

$championHTML = substr($championListHTML, $start, $end - $start + 1);

// within this section is the list of links to champions

// explode on the </a>s; this will give us a bunch of strings that END in champion names;
// there will be an extra sting on the end (the part after the last </a>), which we discard:
$championSegments = explode('</a>', $championHTML);
array_pop($championSegments);

foreach($championSegments as $championSegment)
{
	// there's a bunch of horrible stuff before each champion name; ex: "blablabla<a href=\"etc\">CHAMPION NAME HERE"
	// so we find that last >, and get the string from there to the end
	
	$start = strrpos($championSegment, '>');
	$championName = substr($championSegment, $start + 1);
	
	$championNames[] = $championName;
}

// we end up with an extra, weird URL on the end, from elsewhere in the HTML; get rid of it:
array_pop($championNames);

echo '  Done!<br />';

////

echo 'Scraping Champion data...<br />';

$championData = array();

foreach($championNames as $championName)
{
	$fileName = str_replace(' ', '_', $championName);
	
	echo '  ' . $championName . '<br />';

	$html = @file_get_contents(str_replace(' ', '_', $fileName) . '.cached');
	
	if($html === false)
	{
		$html = @file_get_contents('http://leagueoflegends.wikia.com/wiki/' . str_replace(' ', '_', $fileName));
		
		if($html === false)
		{
			echo '  Could not import ' . $championName . '<br />';
			continue;
		}
		
		$h = fopen($fileName . '.cached', 'w');
		fwrite($h, $html);
		fclose($h);
	}
	
	// similar to before, we're slicing down to just the area we want; in this case, the champion statistics
	$start = strpos($html, '<table ', strpos($html, '>Statistics<'));
	$end = strpos($html, '</table>', $start);

	// we're trimming out that first '>Statistics' bit now
	$infoHTML = substr($html, $start, $end - $start + 1 - strlen('<table'));
	
	// explode on </td>; once again, pop off the stuff after the final </td>
	$infoSegments = explode('</td>', $infoHTML);
	array_pop($infoSegments);

	// we go two at a time; the first is the label, the second is the value
	for($i = 0; $i < count($infoSegments); $i += 2)
	{
		$label = strtolower(trim(strip_tags($infoSegments[$i])));
		$value = trim(strip_tags($infoSegments[$i + 1]));
		
		// the wiki gives all stats as they are at level 0, EXCEPT attack speed, which is given as its level 1
		// value. since champions start at level 1, we must make adjustments to all stats (except attack speed)
		// to get them to their level 1 value
		switch($label)
		{
			case 'health':
				$healthPerLevel = (float)substr($value, strpos($value, '(') + 1);
				$health = (float)$value + $healthPerLevel;
				break;
			
			case 'attack damage':
				$ADPerLevel = (float)substr($value, strpos($value, '(') + 1);
				$AD = (float)$value + $ADPerLevel;
				break;
			
			case 'health regen.':
				$healthRegenPerLevel = (float)substr($value, strpos($value, '(') + 1);
				$healthRegen = (float)$value + $healthRegenPerLevel;
				break;
			
			// again, attack speed is special: it's level-1 value is given, instead of its level-0,
			// so we don't add (well, multiply) in 1 level worth of attack speed
			case 'attack speed [*]':
				$attackSpeedPerLevel = (float)substr($value, strpos($value, '(') + 1);
				$attackSpeed = (float)$value;
				break;
			
			case 'mana':
				$manaPerLevel = (float)substr($value, strpos($value, '(') + 1);
				$mana = (float)$value + $manaPerLevel;
				$resourceName = 'mana';
				break;
			
			case 'energy': // Akali, Kennen, Lee Sin, Shen, Zed
				$resourceName = 'energy';
				$manaPerLevel = 0;
				$mana = 0;
				$manaRegenPerLevel = 0;
				$manaRegen = 0;
				break;
			
			case 'fury': // Renekton, Trynamere, Shyvana
				$resourceName = 'fury';
				$manaPerLevel = 0;
				$mana = 0;
				$manaRegenPerLevel = 0;
				$manaRegen = 0;
				break;
			
			case 'ferocity': // Rengar
				$resourceName = 'ferocity';
				$manaPerLevel = 0;
				$mana = 0;
				$manaRegenPerLevel = 0;
				$manaRegen = 0;
				break;
			
			case 'heat': // Rumble
				$resourceName = 'heat';
				$manaPerLevel = 0;
				$mana = 0;
				$manaRegenPerLevel = 0;
				$manaRegen = 0;
				break;

			case 'uses health': // Aatrox, Dr. Mundo, Mordekaiser, Vladimir, Zac
				$resourceName = 'health';
				$manaPerLevel = 0;
				$mana = 0;
				$manaRegenPerLevel = 0;
				$manaRegen = 0;
				break;
			
			case 'no resource': // Garen, Katarina, Riven
				$resourceName = 'none';
				$manaPerLevel = 0;
				$mana = 0;
				$manaRegenPerLevel = 0;
				$manaRegen = 0;
				break;
			
			case 'armor':
				$armorPerLevel = (float)substr($value, strpos($value, '(') + 1);
				$armor = (float)$value + $armorPerLevel;
				break;
			
			case 'mana regen.':
				$manaRegenPerLevel = (float)substr($value, strpos($value, '(') + 1);
				$manaRegen = (float)$value + $manaRegenPerLevel;
				break;
			
			case 'energy regen.':
				break;
			
			case 'magic res.':
				$magicResPerLevel = (float)substr($value, strpos($value, '(') + 1);
				$magicRes = (float)$value + $magicResPerLevel;
				break;
			
			case 'ranged':
			case 'melee':
				$range = (float)$value;
				break;
			
			
			case 'mov. speed':
				$moveSpeed = (float)$value;
				break;
			
		}
	}
	
	$championData[$championName] = array(
		'name' => $championName,
		'health' => $health,
		'health_per_level' => $healthPerLevel,
		'health_regen' => $healthRegen,
		'health_regen_per_level' => $healthRegenPerLevel,
		'resource_name' => $resourceName,
		'mana' => $mana,
		'mana_per_level' => $manaPerLevel,
		'mana_regen' => $manaRegen,
		'mana_regen_per_level' => $manaRegenPerLevel,
		'range' => $range,
		'attack_damage' => $AD,
		'attack_damage_per_level' => $ADPerLevel,
		'attack_speed' => $attackSpeed,
		'attack_speed_per_level' => $attackSpeedPerLevel,
		'armor' => $armor,
		'armor_per_level' => $armorPerLevel,
		'magic_res' => $magicRes,
		'magic_res_per_level' => $magicResPerLevel,
		'move_speed' => $moveSpeed
	);
}

echo '  Done!<br />';

echo 'Here you go:<br /><br />';

echo json_encode($championData);