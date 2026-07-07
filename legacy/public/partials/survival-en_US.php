<?php

/**
 * Survival plugin project
 * General description of the project
 * 
 * Help Page: Survival main page
 *
 * @link       https://byteway.eu/contact
 * @since      1.0.0
 *
 * @package    Survival
 * @subpackage Survival/public/partials
 */
?>

<h2>Survival</h2>
<p><strong>Location</strong>: Zwolle, the Netherlands, district Westenholte</p>
<p>Every year, dedicated volunteers come together to organize a survival event in the district. The primary audience for this event consists of children from primary schools. These enthusiastic youngsters have the opportunity to enroll as a teams, with the guidance of a volunteer parent who serves as their coach. So, the team’s average age can range from 10 to 14 years old.</p>
<p>During each survival event, two volunteer referees play a crucial role. They oversee the enforcement of all rules throughout the event, ensuring fair play. Additionally, the referees are responsible for assigning scores to each team. At the end of the day, the referees deliver the final score for their event. This means that the position of all Teams for that survival is known. </p>
<p>The final score is determined by the Survival Officer On Duty. The award ceremony is done by an official.</p>
<p>Rather than managing survival scores using an Excel document, the goal is to digitize the scoring process by putting all scores online. Fortunately, the Westenholte District Committee already has a WordPress website in place. </p>

<h2>Solution proposal</h2>
<p>Build a WordPress <a href="https://developer.wordpress.org/plugins/intro/what-is-a-plugin/" target="blank">plugin</a>, make use of all the advantages that come with using a WordPress plugin - instead of adding new behaviour to the website by changing the current WordPress Theme. The plugin has an administration part and a public part. In the administration part all Survival related data can be edited. The plugin contains <a href="https://developer.wordpress.org/plugins/shortcodes/" target="blank">shortcodes</a> that can be used in <a href="https://wordpress.org/documentation/article/create-pages" target="blank">WordPress pages</a>.</p>
<p>The Survival project contains the following entities:</p>
<ul>
<li><strong>Survival </strong>&#8211; Actual match between two teams, start time, location, match rules</li>
<li><strong>Referee </strong>&#8211; Name, Email, Mobile number, Survival Id</li>
<li><strong>Team </strong>&#8211; Name, number of team members, mobile number contact person</li>
<li><strong>Score </strong>&#8211; Team, Survival, score</li>
</ul>

<h3>Referee</h3>
<ul>
<li>can update a team&#8217;s score for a Survival through a web page</li>
<li>must report to the competition secretariat at 8.00 am (tent/first aid post)</li>
<li>is registered as referee, with email address</li>
<li>is assigned to one survival throughout the day</li>
</ul>

<h3>Team leaders</h3>
<ul>
<li>must report to the competition secretariat at 8.15 am (tent/first aid post)</li>
<li>will receive a joker at the competition secretariat</li>
<li>are registered with their mobile number and email</li>
<li>accompany a team throughout the day</li>
</ul>

<h3>Teams</h3>
<ul>
<li>From 1 to maximum 20 Teams</li>
</ul>

<h4>Tips:</h4>
<ul>
<li>Put on old shoes.</li>
<li>These may not be taken off during the games.</li>
<li>Bring dry clothes.</li>
</ul>

<h4>Joker</h4>
<ul>
<li>The joker can be used once in any game, which means:double points can be earned.</li>
<li>The joker is handed in to the referee of the relevant game.</li>
<li>The joker can only be used once.</li>
</ul>

<br><p>Throughout the day, there are two groups per game. Only at the signal of the lead can you walk to the next game. At 11:55 a.m., the team leaders can come to the tent to pick up drinks for their team. After the last game at 3:30 p.m., the teams have to take all the materials to the tent. The results will be announced as soon as possible after the last game. All stay with the tent.</p>

<h4>Default time slots</h4>
<ol>
<li>09:00 - 09:25</li>
<li>09:30 - 09:55</li>
<li>10:00 - 10:25</li>
<li>10:30 - 10:55</li>
<li>11:00 - 11:25</li>
<li>11:30 - 11:55</li>
<li>Pauze</li>
<li>13:00 - 13:25</li>
<li>13:30 - 13:55</li>
<li>14:00 - 14:25</li>
<li>15:00 - 15:25</li>
<li>15:30 - 15:55</li>
<li>14:00 - 14:25</li>
<li>14:30 - 15:55</li>
<li>15:00 - 15:25</li>
</ol>