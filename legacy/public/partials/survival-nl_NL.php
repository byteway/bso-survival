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
<p><strong>Plaats</strong>: Zwolle, Nederland, district Westenholte</p>
<p>Elk jaar komen toegewijde vrijwilligers samen om een survival-evenement te organiseren in het district. Het belangrijkste publiek voor dit evenement bestaat uit kinderen van basisscholen. Deze enthousiaste jongeren hebben de mogelijkheid om zich in te schrijven als teams, met begeleiding van een vrijwillige ouder die als coach fungeert. De gemiddelde leeftijd van het team varieert dus van 10 tot 14 jaar.</p>
<p>Tijdens elk survival-evenement spelen twee vrijwillige scheidsrechters een cruciale rol. Ze zien toe op de handhaving van alle regels gedurende het evenement, zodat er eerlijk wordt gespeeld. Daarnaast zijn de scheidsrechters verantwoordelijk voor het toekennen van scores aan elk team. Aan het einde van de dag leveren de scheidsrechters de eindstand voor hun evenement. Dit betekent dat de positie van alle teams voor die survival bekend is.</p>
<p>De uiteindelijke score wordt bepaald door de dienstdoende Survival Officer. De prijsuitreiking wordt gedaan door een officiële persoon.</p>
<p>In plaats van het beheren van survival-scores met een Excel-document, is het doel om het scoringsproces te digitaliseren door alle scores online te plaatsen. Gelukkig heeft het Westenholte District Comité al een WordPress-website.</p>

<h2>Oplossingsvoorstel</h2>
<p>Bouw een WordPress <a href="https://developer.wordpress.org/plugins/intro/what-is-a-plugin/" target="blank">plugin</a> en maak gebruik van alle voordelen die het gebruik van een WordPress-plugin met zich meebrengt - in plaats van nieuw gedrag toe te voegen aan de website door het huidige WordPress-thema te wijzigen. De plugin heeft een administratief gedeelte en een openbaar gedeelte. In het administratieve gedeelte kunnen alle gegevens met betrekking tot Survival worden bewerkt. De plugin bevat <a href="https://developer.wordpress.org/plugins/shortcodes/" target="blank">shortcodes</a> die kunnen worden gebruikt in <a href="https://wordpress.org/documentation.v.t.rticle/create-pages" target="blank">WordPress-pagina's</a>.</p>
<p>Het Survival-project bevat de volgende entiteiten:</p>
<ul>
<li><strong>Survival </strong>&#8211; Feitelijke wedstrijd tussen twee teams, starttijd, Plaats, wedstrijdregels</li>
<li><strong>Scheidsrechter </strong>&#8211; Naam, E-mail, Mobiel nummer, Survival Id</li>
<li><strong>Team </strong>&#8211; Naam, aantal teamleden, mobiel nummer contactpersoon</li>
<li><strong>Score </strong>&#8211; Team, Survival, score</li>
</ul>

<h3>Scheidsrechter</h3>
<ul>
<li>kan de score van een team voor een Survival bijwerken via een webpagina</li>
<li>moet zich om 8.00 uur melden bij het wedstrijdsecretariaat (tent/ehbo-post)</li>
<li>is geregistreerd als scheidsrechter, met e-mailadres</li>
<li>is toegewezen aan één survival gedurende de dag</li>
</ul>

<h3>Teamleiders</h3>
<ul>
<li>moeten zich om 8.15 uur melden bij het wedstrijdsecretariaat (tent/ehbo-post)</li>
<li>krijgen een joker bij het wedstrijdsecretariaat</li>
<li>zijn geregistreerd met hun mobiele nummer en e-mail</li>
<li>begeleiden een team gedurende de dag</li>
</ul>

<h3>Teams</h3>
<ul>
<li>Van 1 tot maximaal 20 teams</li>
</ul>

<h4>Tips:</h4>
<ul>
<li>Trek oude schoenen aan.</li>
<li>Deze mogen tijdens de spellen niet uitgetrokken worden.</li>
<li>Neem droge kleding mee.</li>
</ul> 

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