=== Plugin Name ===
Contributors: byteway
Donate link: https://byteway.eu/contact/
Tags: comments, spam
Requires at least: 3.0.1
Tested up to: 3.4
Stable tag: 4.3
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

== Short Description ==

The purpose of the survival plugin is to streamline the scoring process for the annual survival event in Zwolle, the Netherlands. 
The organizers aim to digitize the scoring system, replacing manual Excel documents. 
The plugin has an administration part for editing survival-related data and shortcodes for WordPress pages. 
It will facilitate communication between referees, team leaders, and teams, ensuring fair play and efficient management during the event. 
Additionally, teams are able to see the interim score and this will increase the combativeness between teams.


== Description ==

Survival
--------

**Location**: Zwolle, the Netherlands, district Westenholte

Every year, dedicated volunteers come together to organize a survival event in the district. The primary audience for this event consists of children from primary schools. These enthusiastic youngsters have the opportunity to enroll as a teams, with the guidance of a volunteer parent who serves as their coach. So, the team’s average age can range from 10 to 14 years old.

During each survival event, two volunteer referees play a crucial role. They oversee the enforcement of all rules throughout the event, ensuring fair play. Additionally, the referees are responsible for assigning scores to each team. At the end of the day, the referees deliver the final score for their event. This means that the position of all Teams for that survival is known.

The final score is determined by the Survival Officer On Duty. The award ceremony is done by an official.

Rather than managing survival scores using an Excel document, the goal is to digitize the scoring process by putting all scores online. Fortunately, the Westenholte District Committee already has a WordPress website in place.

Solution proposal
-----------------

Build a WordPress [plugin](https://developer.wordpress.org/plugins/intro/what-is-a-plugin/), make use of all the advantages that come with using a WordPress plugin - instead of adding new behaviour to the website by changing the current WordPress Theme. The plugin has an administration part and a public part. In the administration part all Survival related data can be edited. The plugin contains [shortcodes](https://developer.wordpress.org/plugins/shortcodes/) that can be used in [WordPress pages](https://wordpress.org/documentation/article/create-pages).

The Survival project contains the following entities:

*   **Survival** – Actual match between two teams, start time, location, match rules
*   **Referee** – Name, Email, Mobile number, Survival Id
*   **Team** – Name, number of team members, mobile number contact person
*   **Score** – Team, Survival, score

### Referee

*   can update a team’s score for a Survival through a web page
*   must report to the competition secretariat at 8.00 am (tent/first aid post)
*   is registered as referee, with email address
*   is assigned to one survival throughout the day

### Team leaders

*   must report to the competition secretariat at 8.15 am (tent/first aid post)
*   will receive a joker at the competition secretariat
*   are registered with their mobile number and email
*   accompany a team throughout the day

### Teams

*   From 1 to maximum 20 Teams

#### Tips:

*   Put on old shoes.
*   These may not be taken off during the games.
*   Bring dry clothes.

#### Joker

*   The joker can be used once in any game, which means:double points can be earned.
*   The joker is handed in to the referee of the relevant game.
*   The joker can only be used once.


Throughout the day, there are two groups per game. Only at the signal of the lead can you walk to the next game. At 11:55 a.m., the team leaders can come to the tent to pick up drinks for their team. After the last game at 3:30 p.m., the teams have to take all the materials to the tent. The results will be announced as soon as possible after the last game. All stay with the tent.

#### Default time slots

1. 09:00 - 09:25
1. 09:30 - 09:55
1. 10:00 - 10:25
1. 10:30 - 10:55
1. 11:00 - 11:25
1. 11:30 - 11:55
1. Pauze
1. 13:00 - 13:25
1. 13:30 - 13:55
1. 14:00 - 14:25
1. 15:00 - 15:25
1. 15:30 - 15:55
1. 14:00 - 14:25
1. 14:30 - 15:55
1. 15:00 - 15:25

== Installation ==

This section describes how to install the plugin and get it working.

1. Download the plugin [bso-survival.zip](https://github.com/byteway/bso-survival)
1. Upload `bso-survival` to the `/wp-content/plugins/` directory
1. Activate the plugin through the 'Plugins' menu in WordPress
1. Check the Survival Settings, and place [shortcodes] in your page(s)

== Frequently Asked Questions ==

= A question that someone might have =

An answer to that question.

= What about foo bar? =

Answer to foo bar dilemma.

== Screenshots ==

1. ![Example survival menu](assets/screenshot-01.png)
1. ![Survival and referee](assets/screenshot-02.png)
1. ![Survival score](assets/screenshot-03.png)
1. ![Score](assets/screenshot-04.png)
1. ![Team score](assets/screenshot-05.png)
1. ![Survival settings](assets/screenshot-06.png)
1. ![General settings](assets/screenshot-07.png)
1. ![Shortcode settings](assets/screenshot-08.png)
1. ![Help shortcodes](assets/screenshot-09.png)
1. ![Survival Tools](assets/screenshot-10.png)
1. ![General tools](assets/screenshot-11.png)
1. ![Update survival](assets/screenshot-12.png)
1. ![Overview team position](assets/screenshot-13.png)


== Changelog ==

= 1.0 =
* Start with GitHub Boiler template https://github.com/fsylum/outdated-notice
* Implemented Activate/Deactivate for plugin: Deactivate deletes all data!
* Referee security role
* Admin settings section: page URL for survival integration, calculate overall position
* public shortcodes: team, referee, survival, score, update score, help
* readme, screenshots and help descriptions

== Upgrade Notice ==

= 0.5 =
* WYSIWIG local test version, CSS and JS for public/admin HTML
* Survival table structure
* Calculated field for time value
* Auto numbering field for ID'same
* logic for planning proposal

== Arbitrary section ==

*TODO*
1. Add referee function to recalculate the points of all teams per survival. 
1. Add SVG images to survival help pages
1. Add registration page for referee, team coach
1. Update the Survival organizers and referees, plan survival plugin training

*Nice to have*
1. Add a way to thank sponsors, show sponsor logo
1. Create survival badges: winning team, team coach, referee, organizer
1. Email all participants their earned badge
