<?php
declare(strict_types=1);

function p85_es_fr_to_es_pairs(): array
{
    static $pairs = null;
    if ($pairs !== null) {
        return $pairs;
    }
    $raw = <<<'TXT'
PageSpeed Insights indique « Non évalué » pour les audits WebMCP lorsque Chrome de laboratoire n’a pas navigator.modelContext (Lighthouse ne considère WebMCP comme pris en charge que si cette API existe). C’est une limite du navigateur de labo — pas un échec de Birkly. Birkly injecte toujours le script d’outils, ajoute toolname/tooldescription sur les formulaires autorisés et enregistre les outils dans les navigateurs compatibles WebMCP. Vérifiez localement avec les étapes ci-dessous. Le contrôle llms.txt séparé nécessite /llms.txt sur votre site (servi automatiquement depuis un Guide de l’agent publié).|PáginaSpeed Insights indique « No evalue » para les audits WebMCP cuando Chrome de laboratoire n'a pas navigator.modelContext (Lighthouse ne considere WebMCP comme pris en charge que si cette API existe). C'est une limite du navigateur de labo — pas un echec de Birkly. Birkly injecte toujours le script d'outils, ajoute toolname/tooldescription en les formulaires autorises y enregistre les outils en les navigateurs compatibles WebMCP. Verifiez localement con les pasos ci-dessous. Le controle llms.txt separe necessitio /llms.txt en su sitio (servi automatiquement desde un Guía de l'agent publie).
Démarrer parce que cela permet aux assistants de l'IA de faire des choses sur votre site en direct. Activez-le lorsque vous êtes prêt, choisissez exactement les actions à autoriser et désactivez-le à tout moment. Les actions sensibles — comme le paiement ou la connexion — demandent toujours au visiteur de confirmer.|Demarrer parce que cela permet aux assistants del IA de faire des choses en su sitio en direct. Activez-le cuando usted etes pret, choisissez exactement les actions a autoriser y desactivez-le a tout moment. Les actions sensibles — comme le paiement o la connexion — demandent toujours au visitiour de confirmer.
Votre site est hébergé en dehors de Birkly. Ajoutez l'extrait de code client Birkly ci-dessous à chaque page — il expose automatiquement votre guide d'agent publié (JSON, llms.txt, /for-agents et liens de découverte) à partir de votre URL Birkly. Aucun téléchargement de fichier ou configuration de serveur requis.|Su sitio est heberge en dehors de Birkly. Ajoutez el extrait de code client Birkly ci-dessous a chaque página — il expose automatiquement su guide de agent publie (JSON, llms.txt, /for-agents y liens de decouverte) a partir de su URL Birkly. Ninguno telechargement de archivo o configuration de serveur requis.
WebMCP permet aux assistants IA du navigateur du visiteur d'utiliser des actions claires sur votre site Web en direct (comme la soumission d'un formulaire de contact ou l'ajout au panier) au lieu de deviner à partir de la page. Il est éteint jusqu'à ce que vous l'allumiez.|WebMCP permet aux assistants IA du navigateur du visitiour de utiliser des actions claires en su sitio Web en direct (comme la soumission de un formulaire de contact o el ajout au panier) au lieu de deviner a partir de la página. Il est eteint hasta ce que usted el allumiez.
Rédiger des approbations : les confirmations d'écriture MCP se produisent dans votre application d'IA (Curseur, Claude) — pas dans l'onglet Approbations Birkly. L'assistant intégré utilise un flux d'approbation séparé sous Paramètres Assistant → intégré.|Rediger des approbations : les confirmations de ecriture MCP se produisent en su application de IA (Curseur, Claude) — pas en el onglet Approbations Birkly. L'assistant integre utilise un flux de approbation separe sous Configuración Assistant → integre.
Lorsqu'elle est désactivée, cette collection ne peut être lue que par les pages rendues par Birkly elle-même. Activez cette option uniquement si un site externe doit la récupérer. Tout ce qui est lisible de cette manière doit être traité comme public.|Lorsqu'elle est desactivee, cette collection ne peut etre lue que par les páginas rendues par Birkly elle-meme. Activez cette option uniquement si un sitio externe doit la recuperer. Tout ce qui est lisible de cette maniere doit etre traite comme public.
Claude, ChatGPT et Cursor rejettent les URL de connecteur http ://. Ouvrez Birkly en utilisant https :// sur une adresse publique, puis copiez l'URL du connecteur affichée ici. Les URL HTTP et localhost ne fonctionneront pas dans Claude.|Claude, ChatGPT y Cursor rejettent les URL de connecteur http ://. Ouvrez Birkly en utilisant https :// en une adresse publique, puis copiez el URL du connecteur affichee ici. Les URL HTTP y localhost ne fonctionneront pas en Claude.
Cette page est destinée aux connexions CMS — comment les applications d'IA et l'assistant intégré parlent à votre administrateur Birkly. Il est distinct du Guide de l'agent (ce que l'IA sait de votre entreprise sur le site public).|Cette página est destinee aux connexions CMS — comment les applications de IA y el assistant integre parlent a su administrateur Birkly. Il est distinct du Guía del agent (ce que el IA sait de su entreprise en le sitio public).
Choisissez les actions que les assistants IA peuvent utiliser. Les outils désactivés sont invisibles pour l'IA du visiteur. Les outils Boutique et Événement n'apparaissent que lorsque ces fonctionnalités se trouvent sur vos pages.|Elija les actions que les assistants IA peuvent utiliser. Les outils desactives sont invisibles para el IA du visitiour. Les outils Boutique y Evento no apparaissent que cuando ces fonctionnalites se trouvent en sus páginas.
Sur une version Chromium prise en charge (146+), activez l'indicateur # enable-webmcp-testing et ouvrez une page avec des outils activés. Les outils enregistrés apparaissent dans l'inspecteur de contexte-modèle du navigateur.|Sur une version Chromium prise en charge (146+), activez el indicateur # enable-webmcp-testing y ouvrez une página con des outils actives. Les outils enregistres apparaissent en el inspecteur de contexte-modele du navigateur.
Collez l'extrait sur chaque page. Il charge des modèles et publie automatiquement la découverte du Guide de l'agent pour les IA de chat/recherche sur votre URL Birkly — /.well-known/agent-guide.json, /llms.txt et /for-agents.|Collez el extrait en chaque página. Il charge des modeles y publie automatiquement la decouverte du Guía del agent para les IA de chat/recherche en su URL Birkly — /.well-known/agent-guide.json, /llms.txt y /for-agents.
Les outils du site Web sont construits sur la norme ouverte du navigateur WebMCP, de sorte que les navigateurs et les agents IA peuvent utiliser votre site en toute sécurité — les mêmes actions, avec le visiteur en contrôle.|Les outils du sitio Web sont construits en la norme ouverte du navigateur WebMCP, de sorte que les navigateurs y les agents IA peuvent utiliser su sitio en toute securite — les memes actions, con le visitiour en controle.
Configurez des sous-collections imbriquées qui peuvent être attachées aux entrées de cette sous-collection. Par exemple, une sous-collection « Commentaires » peut avoir des « Réponses » en tant que sous-collection imbriquée.|Configurez des sous-collections imbriquees qui peuvent etre attachees aux entrees de cette sous-collection. Par exemple, une sous-collection « Commentaires » peut avoir des « Reponses » en tant que sous-collection imbriquee.
Configurez les sous-collections qui peuvent être attachées aux entrées de cette collection. Par exemple, une collection d'articles de blog peut avoir des « commentaires » et des « évaluations » en tant que sous-collections.|Configurez les sous-collections qui peuvent etre attachees aux entrees de cette collection. Par exemple, une collection de articles de blog peut avoir des « commentaires » y des « evaluations » en tant que sous-collections.
La demande a expiré (504). Les constructions de sites volumineux peuvent prendre une minute — réessayez, utilisez un modèle plus rapide ou demandez moins de fichiers à la fois (par exemple, « écrivez d'abord index.html »).|La demande a expire (504). Les constructions de sitios volumineux peuvent prendre une minute — reessayez, utilisez un modele plus rapide o demandez moins de archisus a la fois (par exemple, « ecrivez de abord index.html »).
Publié automatiquement sur votre site public — /.well-known/agent-guide.json,/llms.txt, /for-agents, plus un lien Pour les agents dans le pied de page de votre page. Aucun téléchargement ou téléversement n'est nécessaire.|Publicado automatiquement en su sitio public — /.well-known/agent-guide.json,/llms.txt, /for-agents, plus un lien Pour les agents en le pied de página de su página. Ninguno telechargement o televersement no est necessaire.
Les outils de site Web permettent aux assistants IA dans le navigateur d'un visiteur d'utiliser des actions claires sur votre site en direct au lieu de deviner. Votre site fonctionne de la même manière pour tout le monde.|Les outils de sitio Web permettent aux assistants IA en le navigateur de un visitiour de utiliser des actions claires en su sitio en direct au lieu de deviner. Su sitio fonctionne de la meme maniere para tout le monde.
Les agents de service existants sont administrés uniquement jusqu'à ce que vous attribuiez des rôles ou des utilisateurs. Modifiez les Qui peut utiliser les paramètres de chaque agent de service pour partager l'accès.|Les agents de service existants sont administres uniquement hasta ce que usted attribuiez des roles o des utilisateurs. Modifiez les Qui peut utiliser les parametres de chaque agent de service para partager el acces.
Claude Desktop ou Cursor sur cette machine (recommandé avant déploiement cloud) — ajoutez un agent de service ci-dessous, copiez son URL de connecteur, configurez mcp-remote dans l’app desktop (OAuth au premier lien).|Claude Desktop o Cursor en cette machine (recommande avant deploiement cloud) — ajoutez un agent de service ci-dessous, copiez son URL de connecteur, configurez mcp-remote en l'app desktop (OAuth au premier lien).
Projetseiten serverseitig vorrendern für schnelleren First Paint. SSR ist Par défaut für Birkly-gehostete Projete et läuft auf der eingebauten PHP-Engine — aucun Node.js nötig. Externe Sites rendern immer im Client.|Projetseiten serverseitig vorrendern fur schnelleren First Paint. SSR ist Par defaut fur Birkly-gehostete Projete y läuft auf der eingebauten PHP-Engine — aucun Node.js nötig. Externe Sitios rendern immer im Cliente.
Le Guide de l'agent est votre carte publiée pour les systèmes et les agents d'IA. Vous déclarez où réside la vérité, rédigez un résumé concis, liez la preuve et publiez un guide lisible par machine sur votre site.|Le Guía del agent est su carte publiee para les systemes y les agents de IA. Usted declarez ou reside la verite, redigez un resume concis, liez la preuve y publiez un guide lisible par machine en su sitio.
Connectez un fournisseur d'IA pour le bouton de chat dans l'administration. Vous avez besoin d'une clé API de développeur — les abonnements grand public (ChatGPT Plus, Claude Pro) n'incluent pas l'accès à l'API.|Connectez un fournisseur de IA para le bouton de chat en el administration. Usted avez besoin de une cle API de developpeur — les abonnements grand public (ChatGPT Plus, Claude Pro) no incluent pas el acces a el API.
Des applications d'IA comme Claude, Cursor et ChatGPT se connectent à votre CMS Birkly — et non à votre hébergeur de site Web. Configurez un connecteur personnel ou des agents de service partagés ci-dessous.|Des applications de IA comme Claude, Cursor y ChatGPT se connectent a su CMS Birkly — y non a su hebergeur de sitio Web. Configurez un connecteur personnel o des agents de service partages ci-dessous.
Les outils de site Web fonctionnent dans les navigateurs qui les prennent en charge. Lorsqu'un navigateur ne le fait pas, votre page se comporte exactement comme elle le fait aujourd'hui — rien ne se casse.|Les outils de sitio Web fonctionnent en les navigateurs qui les prennent en charge. Lorsqu'un navigateur ne le fait pas, su página se comporte exactement comme elle le fait aujourd'hui — rien ne se casse.
Facultatif : servez les mêmes chemins à partir de votre propre domaine en procurant à Birkly. Seulement nécessaire si vous voulez des URL de guide sur votre domaine marketing au lieu de votre hôte Birkly.|Opcional : servez les memes chemins a partir de su propre domaine en procurant a Birkly. Seulement necessaire si usted voulez des URL de guide en su domaine marketing au lieu de su hote Birkly.
Visualiseur = lecture seule. Éditeur = suggérer des modifications (nécessite souvent votre approbation). Admin = accès complet. Ce rôle s'applique à tous ceux qui se connectent à cet agent de service.|Visualiseur = lecture seule. Editor = suggerer des modifications (necessitio souvent su approbation). Admin = acces complet. Ce role se applique a tous ceux qui se connectent a cet agent de service.
Ihr Feedback hilft, Birkly in der geschlossenen Alpha zu verbessern. Jeder Bericht enthält die aktuelle Page et Browser-Infos zur Nachvollziehbarkeit. Mehr in unserer {{privacy}} et den {{terms}}.|Ihr Feedback hilft, Birkly in der geschlossenen Alpha zu verbessern. Jeder Bericht enthält die aktuelle Página y Browser-Infos zur Nachvollziehbarkeit. Mehr in unserer {{privacy}} y den {{terms}}.
Votre URL Birkly ci-dessus sert toujours le guide en direct. L'extrait de code client est le chemin de configuration zéro pour les sites externes ; le proxy et le téléchargement sont facultatifs.|Su URL Birkly ci-dessus sert toujours le guide en direct. L'extrait de code client est le chemin de configuration zero para les sitios externes ; le proxy y le telechargement sont facultatifs.
Der Server rendert HTML mit der eingebauten PHP-Engine — empfohlen et Par défaut für Birkly-gehostete Projete. Auto nutzt den Server wenn möglich, sonst den Client. Client rendert nur im Browser.|Der Server rendert HTML mit der eingebauten PHP-Engine — empfohlen y Par defaut fur Birkly-gehostete Projete. Auto nutzt den Server wenn möglich, sonst den Cliente. Cliente rendert nur im Browser.
Les connexions IA et l'accès entrant MCP fonctionnent mieux sur un serveur Web en direct avec une URL publique. Vous pouvez les configurer à tout moment dans les paramètres après le déploiement.|Les connexions IA y el acces entrant MCP fonctionnent mieux en un serveur Web en direct con une URL publique. Usted pouvez les configurer a tout moment en les parametres apres le deploiement.
Lorsque quelqu'un interroge un assistant IA sur vous, vos produits ou votre travail, il obtient des réponses fondées sur les sources que vous contrôlez, et non sur des extraits Web aléatoires.|Lorsque quelqu'un interroge un assistant IA en usted, sus produits o su travail, il obtient des reponses fondees en les sources que usted controlez, y non en des extraits Web aleatoires.
Clé API non valide. Assurez-vous d'avoir copié la clé à partir de la console de développement de votre fournisseur — les abonnements ChatGPT Plus et Claude Pro n'incluent pas l'accès à l'API.|Cle API non valide. Assurez-usted de avoir copie la cle a partir de la console de developpement de su fournisseur — les abonnements ChatGPT Plus y Claude Pro no incluent pas el acces a el API.
Configurez la façon dont Birkly envoie les e-mails : notifications de formulaire, reçus de commande, réinitialisations de mot de passe, etc. Vous n'avez besoin de le faire qu'une seule fois.|Configurez la facon dont Birkly envoie les e-mails : notifications de formulaire, recus de commande, reinitialisations de mot de passe, etc. Usted no avez besoin de le faire que une seule fois.
La clé API est requise. Les abonnements grand public (ChatGPT Plus, Claude Pro) n'incluent pas l'accès à l'API — obtenez une clé à partir de la console de développement de votre fournisseur.|La cle API est requise. Les abonnements grand public (ChatGPT Plus, Claude Pro) no incluent pas el acces a el API — obtenez une cle a partir de la console de developpement de su fournisseur.
Liez les résumés à des sources plus approfondies — entrées CMS, pages de produits, places de marché et sites d'évaluation. Les systèmes d'IA peuvent suivre ces liens pour preuve et détails.|Liez les resumes a des sources plus approfondies — entrees CMS, páginas de produits, places de marche y sitios de evaluation. Les systemes de IA peuvent suivre ces liens para preuve y details.
Commencez ici : déclarez où les agents doivent lire la vérité — entrées CMS, sites Web, documents et autres sources canoniques. Les résumés dans les onglets ultérieurs en sont dérivés.|Commencez ici : declarez ou les agents doivent lire la verite — entrees CMS, sitios Web, documents y autres sources canoniques. Les resumes en les onglets ulterieurs en sont derives.
Le connecteur personnel est déjà configuré pour votre site. Copiez son URL et collez-la dans Claude, ChatGPT ou Cursor — chaque membre de l'équipe se connecte avec son propre compte.|Le connecteur personnel est deja configure para su sitio. Copiez son URL y collez-la en Claude, ChatGPT o Cursor — chaque membre del equipe se connecte con son propre compte.
Sur un hôte externe, vous devez ajouter ces balises de script vous-même. Ils ne sont pas automatiques et ne sont pas synchronisés via Git sauf si vous déployez les scripts vous-même.|Sur un hote externe, usted devez añadir ces balises de script usted-meme. Ils ne sont pas automatiques y ne sont pas synchronises via Git sauf si usted deployez les scripts usted-meme.
Ajoutez 1 à 3 revendications factuelles avec une URL de preuve. Le statut pris en charge nécessite des preuves — liez un communiqué de presse, une étude de cas ou une page d'examen.|Ajoutez 1 a 3 revendications factuelles con une URL de preuve. Le statut pris en charge necessitio des preuves — liez un communique de presse, une etude de cas o une página de examen.
Supprime définitivement toutes les collections, entrées, fichiers du site de démarrage et la progression d’intégration. Les comptes utilisateurs et paramètres CMS sont conservés.|Supprime definitivement toutes les collections, entrees, archisus du sitio de demarrage y la progression d'integration. Les comptes utilisateurs y parametres CMS sont conserves.
Points de terminaison MCP partagés avec un rôle CMS fixe — pour les robots d'équipe, les automatisations ou les intégrations qui ne doivent pas utiliser de connexion personnelle.|Points de terminaison MCP partages con un role CMS fixe — para les robots de equipe, les automatisations o les integrations qui ne doivent pas utiliser de connexion personnelle.
Téléchargez des fichiers PDF, des documents Word, des feuilles de calcul ou des fichiers texte. Ils sont stockés dans les médias et enregistrés en tant que sources documentaires.|Telechargez des archisus PDF, des documents Word, des feuilles de calcul o des archisus texte. Ils sont stockes en les medias y enregistres en tant que sources documentaires.
Aperçu de l’apparence des valeurs par défaut dans les résultats de recherche et les partages sociaux. Les remplacements par URL se gèrent dans les fiches de recherche ci-dessous.|Vista previa de l'apparence des valeurs par defaut en les resultats de recherche y les partages sociaux. Les remplacements par URL se gerent en les fiches de recherche ci-dessous.
Intègre JSON-LD structuré aux côtés de l'exportation publique afin que les moteurs de recherche et les robots d'exploration de l'IA puissent analyser les données de votre guide.|Integre JSON-LD structure aux cotes del exportation publique afin que les moteurs de recherche y les robots de exploration del IA puissent analyser les donnees de su guide.
Sur un site hébergé par Birkly, le script Tools est ajouté automatiquement lorsqu'il est activé. Sur un hôte externe, vous ajoutez vous-même une balise de script — voir Publier.|Sur un sitio heberge par Birkly, le script Tools est ajoute automatiquement lorsqu'il est active. Sur un hote externe, usted ajoutez usted-meme une balise de script — voir Publicar.
Le Guide de l'agent est la fiche d'information publique sur votre entreprise pour l'IA. Vous le modifiez ici ; les IA des visiteurs peuvent le lire à partir de votre site Web.|Le Guía del agent est la fiche de information publique en su entreprise para el IA. Usted le modifiez ici ; les IA des visitiours peuvent le lire a partir de su sitio Web.
Birkly MCP est la façon dont les applications d'IA (Claude, Cursor, ChatGPT, …) se connectent à votre administrateur/CMS Birkly pour vous aider à créer et à gérer du contenu.|Birkly MCP est la facon dont les applications de IA (Claude, Cursor, ChatGPT, …) se connectent a su administrateur/CMS Birkly para usted aider a crear y a gerer du contenido.
Legen Sie fest, wie dieser Hostname für die Auslieferung genutzt wird. Registrierung von <code>birkly.io</code> aktiviert auch <code>www.birkly.io</code> für CORS et Routing.|Legen Sie fest, wie dieser Hostname fur die Auslieferung genutzt wird. Registrierung von <code>birkly.io</code> aktiviert auch <code>www.birkly.io</code> fur CORS y Routing.
Auto nutzt die Site-Root für SSR, Unterverzeichnis-Installationen et saubere URLs; sonst index.html wenn vorhanden. Marsk et ähnliche Templates brauchen meist die Site-Root.|Auto nutzt die Sitio-Root fur SSR, Unterverzeichnis-Installationen y saubere URLs; sonst index.html wenn vorhanden. Marsk y ähnliche Templates brauchen meist die Sitio-Root.
Identifiant compatible URL (lettres ASCII, chiffres et tirets). Les titres acceptent toute langue ; les titres non latins nécessitent un slug manuel pour des URL lisibles.|Identifiant compatible URL (lettres ASCII, chiffres y tirets). Les titres acceptent toute langue ; les titres non latins necessitiont un slug manuel para des URL lisibles.
Lorsque de nombreuses entrées de collection partagent un même fichier modèle, connectez la collection ici. Les entrées publiées apparaissent alors dans la liste ci-dessus.|Lorsque de nombreuses entrees de collection partagent un meme archivo modele, connectez la collection ici. Les entrees publiees apparaissent alors en la liste ci-dessus.
Aucune URL de site public n'est encore configurée. Définissez l'URL de votre site public dans Paramètres Livraison → du site pour voir les points de terminaison en direct.|Ninguna URL de sitio public no est encore configuree. Definissez el URL de su sitio public en Configuración Envío → du sitio para voir les points de terminaison en direct.
Copiez l'URL du connecteur ci-dessous et ajoutez-la en tant que serveur MCP distant dans votre application d'IA — laissez l'ID client/secret vide (OAuth est automatique).|Copiez el URL du connecteur ci-dessous y ajoutez-la en tant que serveur MCP distant en su application de IA — laissez el ID client/secret vide (OAuth est automatique).
Boostez votre flux de travail avec un assistant IA. Il peut répondre à des questions sur votre contenu, vous aider à rédiger des entrées et vous guider à travers Birkly.|Boostez su flux de travail con un assistant IA. Il peut repondre a des questions en su contenido, usted aider a rediger des entrees y usted guider a travers Birkly.
Enregistrez des domaines pour accéder au contenu CMS via l’API. Activez l’analytique auto-hébergée pour suivre les statistiques des visiteurs en toute confidentialité.|Enregistrez des domaines para acceder au contenido CMS via l'API. Activez l'analytique auto-hebergee para suivre les statistiques des visitiours en toute confidentialite.
URL publiques de votre site et leur apparence possible dans les résultats de recherche. Actualisez depuis les fichiers du site pour analyser les modèles dans project/.|URL publiques de su sitio y leur apparence possible en les resultats de recherche. Actualisez desde les archisus du sitio para analyser les modeles en project/.
Legen Sie fest, wie viele Besucherdaten Birkly erfasst. Mit „Paramètres enregistrer“ oben anwenden. Minimal+ aktiviert Tracking für Ihre Site (inkl. localhost-Aperçu).|Legen Sie fest, wie viele Besucherdaten Birkly erfasst. Mit „Configuración guardar“ oben anwenden. Minimal+ aktiviert Tracking fur Ihre Sitio (inkl. localhost-Vista previa).
Où les IA lisent votre guide, si les outils du site Web sont activés et comment les applications d'IA se connectent — adaptées à la façon dont votre site est hébergé.|Ou les IA lisent su guide, si les outils du sitio Web sont actives y comment les applications de IA se connectent — adaptees a la facon dont su sitio est heberge.
Après la publication, votre guide est disponible sur une URL publique. Les chatbots et les agents peuvent le lire au lieu de le deviner à partir de pages dispersées.|Apres la publicación, su guide est disponible en une URL publique. Les chatbots y les agents peuvent le lire au lieu de le deviner a partir de páginas dispersees.
Ajoutez au moins une source avec un rôle narratif — votre page À propos, votre page d'accueil ou votre URL de site Web principal. Les agents les lisent en premier.|Ajoutez au moins une source con un role narratif — su página À propos, su página de accueil o su URL de sitio Web principal. Les agents les lisent en premier.
Permettez aux assistants IA dans le navigateur du visiteur d’utiliser des actions claires sur votre site en direct (formulaires, panier, RSVP) au lieu de deviner.|Permettez aux assistants IA en le navigateur du visitiour d'utiliser des actions claires en su sitio en direct (formulaires, panier, RSVP) au lieu de deviner.
Lorsque cette option est activée, votre Guide de l'agent publié est affiché sur votre site public pour que les systèmes et les agents d'IA puissent le découvrir.|Lorsque cette option est activee, su Guía del agent publie est affiche en su sitio public para que les systemes y les agents de IA puissent le decouvrir.
Impossible d'atteindre Ollama. Assurez-vous qu'il est en cours d'exécution et que l'URL est correcte à partir du serveur Birkly (pas seulement votre navigateur).|Impossible de atteindre Ollama. Assurez-usted que il est en cours de execution y que el URL est correcte a partir du serveur Birkly (pas seulement su navigateur).
Ajoutez un contexte pour les ressources multimédias de votre médiathèque — choisissez des fichiers et décrivez comment les systèmes d'IA doivent les interpréter.|Ajoutez un contexte para les ressources multimedias de su mediatheque — choisissez des archisus y decrivez comment les systemes de IA doivent les interpreter.
Les envois de formulaires et les e-mails de plugins n'atteindront pas une véritable boîte de réception tant que vous n'aurez pas configuré l'e-mail ci-dessous.|Les envois de formulaires y les e-mails de plugins no atteindront pas une veritable boite de reception tant que usted no aurez pas configure el e-mail ci-dessous.
Les collections contiennent le contenu de votre site : articles de blog, produits, pages, etc. Commencez à partir de zéro ou installez un modèle de démarrage.|Les collections contiennent le contenido de su sitio : articles de blog, produits, páginas, etc. Commencez a partir de zero o installez un modele de demarrage.
Widerrufen gibt den Sitzplatz auf dieser Installation frei, damit Sie denselben Licenceschlüssel auf einer anderen Site Web aktivieren ou installieren können.|Widerrufen gibt den Sitzplatz auf dieser Installation frei, damit Sie denselben Licenceschlussel auf einer anderen Sitio Web aktivieren o installieren können.
Licence auf dieser Site Web widerrufen? Der Sitzplatz wird freigegeben, damit Sie denselben Schlüssel auf einer anderen Birkly-Installation aktivieren können.|Licence auf dieser Sitio Web widerrufen? Der Sitzplatz wird freigegeben, damit Sie denselben Schlussel auf einer anderen Birkly-Installation aktivieren können.
Connectez Claude ou ChatGPT avec vos propres autorisations Birkly. Chaque membre de l'équipe utilise cette même URL, mais se connecte avec son propre compte.|Connectez Claude o ChatGPT con sus propres autorisations Birkly. Chaque membre del equipe utilise cette meme URL, mais se connecte con son propre compte.
Fichiers unter <code>project/</code> mit GitHub, GitLab ou einem Git-Remote synchronisieren. CMS-Inhalte, Médias, Utilisateur et Plugins sind nie enthalten.|Archisus unter <code>project/</code> mit GitHub, GitLab o einem Git-Remote synchronisieren. CMS-Inhalte, Medios, Usuario y Plugins sind nie enthalten.
Liste de contrôle sur la façon dont les IA de chat et de recherche peuvent trouver votre guide publié via des pages indexées, un plan du site et robots.txt.|Liste de controle en la facon dont les IA de chat y de recherche peuvent trouver su guide publie via des páginas indexees, un plan du sitio y robots.txt.
Ouvrez une page de votre site sur laquelle des outils sont activés. Confirmez que les formulaires affichent le nom de l'outil/tooldescription dans Inspect.|Ouvrez une página de su sitio en laquelle des outils sont actives. Confirmez que les formulaires affichent le nom del outil/tooldescription en Inspect.
Aucune page autonome trouvée dans project/. Les fiches peuvent provenir uniquement de modèles de collection, ou les modèles peuvent être hors de project/.|Ninguna página autonome trouvee en project/. Les fiches peuvent provenir uniquement de modeles de collection, o les modeles peuvent etre hors de project/.
Invitations-E-mails benötigen eine E-mail-Connexion unter Paramètres → Connexions. Ohne diese „Invitationslink erstellen“ nutzen et die URL selbst teilen.|Invitations-E-mails benötigen eine E-mail-Conexión unter Configuración → Conexións. Ohne diese „Invitationslink erstellen“ nutzen y die URL selbst teilen.
Aucune livraison de site n'est encore configurée. Choisissez comment votre site est livré dans Paramètres Livraison → du site, puis revenez pour publier.|Ninguna livraison de sitio no est encore configuree. Elija comment su sitio est livre en Configuración Envío → du sitio, puis revenez para publicar.
Ce titre ne peut pas produire un slug ASCII. Saisissez un slug manuel (p. ex. texte romanisé ou <code>post-2026</code>) ou l’ID de l’entrée sera utilisé.|Ce titre ne peut pas produire un slug ASCII. Introduzca un slug manuel (p. ex. texte romanise o <code>post-2026</code>) o l'ID de l'entree sera utilise.
Server-Rendering ist deaktiviert, wenn Site delivery pas „In Birkly (project/)“ ist. Modus unter Paramètres → Site delivery ändern, um SSR zu aktivieren.|Server-Rendering ist deaktiviert, wenn Sitio delivery pas „In Birkly (project/)“ ist. Modus unter Configuración → Sitio delivery ändern, um SSR zu aktivieren.
Enregistrez des domaines pour autoriser l’accès API et l’analytique facultative. Gérez les domaines ci-dessous ou consultez les totaux en un coup d’œil.|Enregistrez des domaines para autoriser l'acces API y l'analytique facultative. Gerez les domaines ci-dessous o consultez les totaux en un coup d'oeil.
Les approbations d'écriture sont gérées dans l'application d'IA qui se connecte (par exemple, les autorisations de l'outil Claude), et non dans Birkly.|Les approbations de ecriture sont gerees en el application de IA qui se connecte (par exemple, les autorisations del outil Claude), y non en Birkly.
Ihr Feedback hilft, Birkly in der geschlossenen Alpha zu verbessern. Jeder Bericht enthält die aktuelle Page et Browser-Infos zur Nachvollziehbarkeit.|Ihr Feedback hilft, Birkly in der geschlossenen Alpha zu verbessern. Jeder Bericht enthält die aktuelle Página y Browser-Infos zur Nachvollziehbarkeit.
Pas encore de pages répétitives. Les modèles avec <meta name="birkly-collection"> sont détectés lors de l’actualisation depuis les fichiers du site.|Pas encore de páginas repetitives. Les modeles con <meta name="birkly-collection"> sont detectes cuando de l'actualisation desde les archisus du sitio.
Les administrateurs voient toujours tous les agents de service. Sélectionnez les rôles et/ou les utilisateurs individuels qui peuvent se connecter.|Les administrateurs voient toujours tous les agents de service. Seleccione les roles et/ou les utilisateurs individuels qui peuvent se connecter.
Expose le résumé, les produits et les points de terminaison de l'API de revendications qui renvoient des instantanés JSON de votre contexte publié.|Expose le resume, les produits y les points de terminaison del API de revendications qui renvoient des instantanes JSON de su contexte publie.
Fonctionne dans les navigateurs qui prennent en charge les outils de site Web. Les autres visiteurs voient votre site exactement comme aujourd'hui.|Fonctionne en les navigateurs qui prennent en charge les outils de sitio Web. Les autres visitiours voient su sitio exactement comme aujourd'hui.
Corrigez la grammaire et l'orthographe dans les principaux champs de texte de cette entrée. Énumérez les modifications suggérées avant de postuler.|Corrigez la grammaire y el orthographe en les principaux champs de texte de cette entree. Énumerez les modifications suggerees avant de postuler.
Les connecteurs cloud (Claude / ChatGPT dans le navigateur) n’atteignent pas http ://localhost — tunnel HTTPS ou attendez un site public en HTTPS.|Les connecteurs cloud (Claude / ChatGPT en le navigateur) n'atteignent pas http ://localhost — tunnel HTTPS o attendez un sitio public en HTTPS.
Le contenu reste dans le CMS, sur votre site ou dans des fichiers liés. Le Guide de l'agent fait référence à ces sources au lieu de les dupliquer.|Le contenido reste en le CMS, en su sitio o en des archisus lies. Le Guía del agent fait reference a ces sources au lieu de les dupliquer.
L'édition rend votre Guide de l'agent structuré disponible sur votre site public. Cela ne garantit pas que tous les systèmes d'IA le consommeront.|L'edition rend su Guía del agent structure disponible en su sitio public. Cela ne garantit pas que tous les systemes de IA le consommeront.
Publishing écrit votre brouillon à l'URL publique du Guide de l'agent. Vous pouvez mettre à jour à tout moment — republier lorsque vous êtes prêt.|Publishing ecrit su brouillon a el URL publique du Guía del agent. Usted pouvez mettre a jour a tout moment — republicar cuando usted etes pret.
Le fournisseur a rejeté la demande — le modèle peut être retiré. Essayez un autre modèle (par exemple, gemini-2,5-flash pour Gemini). Détails : %s|Le fournisseur a rejete la demande — le modele peut etre retire. Essayez un autre modele (par exemple, gemini-2,5-flash para Gemini). Details : %s
Vous aurez besoin de l'hôte SMTP, du port, du nom d'utilisateur et du mot de passe de votre adresse e-mail ou de votre fournisseur d'hébergement.|Usted aurez besoin del hote SMTP, du port, du nom de utilisateur y du mot de passe de su adresse e-mail o de su fournisseur de hebergement.
Le créateur de site utilise le rôle Admin pour que l'IA connectée puisse lire et écrire des projets/ HTML, CSS et JS aux côtés du contenu du CMS.|Le createur de sitio utilise le role Admin para que el IA connectee puisse lire y ecrire des projets/ HTML, CSS y JS aux cotes du contenido du CMS.
KI-Connexions benötigen die Autorisation <code>manage_project_site</code> (standardmäßig Admin-Rôle), um Projetdateien zu lesen ou zu schreiben.|KI-Conexións benötigen die Autorisation <code>manage_project_sitio</code> (standardmäßig Admin-Role), um Projetdateien zu lesen o zu schreiben.
Téléchargement d'instantanés facultatif pour les hôtes qui ne peuvent pas exécuter JavaScript. Téléchargez à nouveau après chaque republication.|Telechargement de instantanes facultatif para les hotes qui ne peuvent pas executer JavaScript. Telechargez a nuevo apres chaque republicación.
Ce pack de site nécessite des plugins qui ne sont pas installés :

{list}

Installer les plugins requis manquants, puis continuer avec le pack ?|Ce pack de sitio necessitio des plugins qui ne sont pas installes :

{list}

Installer les plugins requis manquants, puis continuer con le pack ?
Vous conservez les pages du SGC, les fichiers PDF et les listes externes comme sources de vérité ; le Guide de l'agent les résume et y renvoie.|Usted conservez les páginas du SGC, les archisus PDF y les listes externes comme sources de verite ; le Guía del agent les resume y y renvoie.
Aucun formulaire public trouvé pour le moment. Les formulaires publics proviennent de collections prêtes à accepter des soumissions publiques.|Ninguno formulaire public trouve para le moment. Les formulaires publics proviennent de collections pretes a accepter des soumissions publiques.
Activez les points de terminaison publics, prévisualisez comment l'IA pourrait répondre aux questions, puis publiez-la lorsque vous êtes prêt.|Activez les points de terminaison publics, previsualisez comment el IA pourrait repondre aux questions, puis publiez-la cuando usted etes pret.
Les chatbots, les agents de site et les outils connectés MCP peuvent lire un guide JSON canonique au lieu de gratter l'ensemble de votre site.|Les chatbots, les agents de sitio y les outils connectes MCP peuvent lire un guide JSON canonique au lieu de gratter el ensemble de su sitio.
Catalogue structuré de ce que vous proposez. Aide les systèmes d'IA à répondre avec précision aux questions sur les produits et les services.|Catalogue structure de ce que usted proposez. Ayuda les systemes de IA a repondre con precision aux questions en les produits y les services.
Sélectionnez les collections sources. AI propose une identité, un positionnement, des produits et des revendications fondés sur ces sources.|Seleccione les collections sources. AI propose une identite, un positionnement, des produits y des revendications fondes en ces sources.
Si cette option est activée, les entrées seront publiées automatiquement. Sinon, ils seront enregistrés en tant que brouillons pour examen.|Si cette option est activee, les entrees seront publiees automatiquement. Sinon, ils seront enregistres en tant que brouillons para examen.
Aidez les moteurs de recherche et les IA basées sur la recherche à trouver et résumer vos pages — balises meta, plan du site et robots.txt.|Ayudaz les moteurs de recherche y les IA basees en la recherche a trouver y resumer sus páginas — balises meta, plan du sitio y robots.txt.
Les e-mails sont écrits sur logs/mail.jsonl. Utilisez-le pendant le développement ; passez à un fournisseur réel avant de passer en ligne.|Les e-mails sont ecrits en logs/mail.jsonl. Utilisez-le pendant le developpement ; passez a un fournisseur reel avant de passer en ligne.
Ausführliche API-Dokumentation (Endpunkte, Formate, Authentifizierung) finden Sie in der Birkly-Dokumentation ou bei Ihrem Administrator.|Ausfuhrliche API-Dokumentation (Endpunkte, Formate, Authentifizierung) finden Sie in der Birkly-Dokumentation o bei Ihrem Administrator.
Choisissez les événements pour lesquels vous souhaitez être notifié. Les notifications apparaissent dans l’icône cloche en haut à droite.|Elija les evenements para lesquels usted souhaitez etre notifie. Les notifications apparaissent en l'icone cloche en haut a droite.
Votre connexion par défaut ({name}) utilise uniquement le journal. Passez au SMTP ou à un service de messagerie avant de passer en ligne.|Su connexion par defaut ({name}) utilise uniquement le journal. Passez au SMTP o a un service de messagerie avant de passer en ligne.
Dans DevTools → Lighthouse, exécutez Agentic Browsing (ou vérifiez l'inspecteur du contexte du modèle) pour voir les outils enregistrés.|Dans DevTools → Lighthouse, executez Agentic Browsing (ou verifiez el inspecteur du contexte du modele) para voir les outils enregistres.
Glissez les widgets pour les réorganiser et utilisez les cases à cocher pour afficher ou masquer les widgets sur votre tableau de bord.|Glissez les widgets para les reorganiser y utilisez les cases a cocher para afficher o masquer les widgets en su tableau de bord.
Les revendications et les preuves vous permettent de publier ce que vous pouvez prouver — avec des URL que les agents peuvent vérifier.|Les revendications y les preuves usted permettent de publicar ce que usted pouvez prouver — con des URL que les agents peuvent verifier.
La clé API est requise. Obtenez-en un sur le portail des développeurs de votre fournisseur (pas à partir d'un abonnement grand public).|La cle API est requise. Obtenez-en un en le portail des developpeurs de su fournisseur (pas a partir de un abonnement grand public).
Pour Claude Desktop, Cursor ou ChatGPT. Chaque personne se connecte avec son propre compte Birkly et obtient ses propres autorisations.|Pour Claude Desktop, Cursor o ChatGPT. Chaque personne se connecte con son propre compte Birkly y obtient ses propres autorisations.
Créez des informations d'identification IAM avec les autorisations d'envoi ses. Utilisez la région où votre identité ses est vérifiée.|Creez des informations de identification IAM con les autorisations de envoi ses. Utilisez la region ou su identite ses est verifiee.
Choisissez les collections sources pour générer une ébauche complète de proposition. Idéal pour les configurations nouvelles ou vides.|Elija les collections sources para generer une ebauche complete de proposition. Ideal para les configurations nuevas o vides.
Publiez un guide structuré afin que les systèmes d'IA puissent découvrir qui vous êtes, ce que vous proposez et ce que vous défendez.|Publiez un guide structure afin que les systemes de IA puissent decouvrir qui usted etes, ce que usted proposez y ce que usted defendez.
Réinitialisez le contenu du site et revenez à l’assistant de configuration. À utiliser pour repartir de zéro sans réinstaller Birkly.|Reinitialisez le contenido du sitio y revenez a l'assistant de configuration. À utiliser para repartir de zero sin reinstaller Birkly.
Vous pouvez éventuellement lier cette installation de CMS à votre compte cloud Birkly. La connexion locale reste toujours disponible.|Usted pouvez eventuellement lier cette installation de CMS a su compte cloud Birkly. La connexion locale reste toujours disponible.
Utilise votre IA connectée à partir de Paramètres → IA. Restez sur les règles uniquement pour les suggestions de base basées sur CMS.|Utilise su IA connectee a partir de Configuración → IA. Restez en les regles uniquement para les suggestions de base basees en CMS.
Remplit toutes les sections manquantes à l'aide de sources CMS lorsqu'elles sont disponibles, ou déduit de votre brouillon existant.|Remplit toutes les sections manquantes a el aide de sources CMS lorsqu'elles sont disponibles, o deduit de su brouillon existant.
N’exposez pas l’admin sur Internet par défaut. Préférez mcp-remote + agent de service, ou un tunnel court si l’UI cloud exige HTTPS.|N'exposez pas l'admin en Internet par defaut. Preferez mcp-remote + agent de service, o un tunnel court si l'UI cloud exige HTTPS.
De nombreux hôtes partagés bloquent ou limitent le courrier PHP (). Préférez le SMTP ou un service de messagerie pour la production.|De nombreux hotes partages bloquent o limitent le courrier PHP (). Preferez le SMTP o un service de messagerie para la production.
Votre nom officiel et votre courte description apparaissent dans le JSON public et aident les agents à vous présenter correctement.|Su nom officiel y su courte description apparaissent en le JSON public y aident les agents a usted presenter correctement.
Aucune source pour le moment. Ajoutez des entrées CMS, une URL de site Web ou des documents que les agents doivent lire en premier.|Ninguna source para le moment. Ajoutez des entrees CMS, une URL de sitio Web o des documents que les agents doivent lire en premier.
Un bot partagé qui agit toujours comme un rôle CMS spécifique (par exemple, toujours éditeur). Vous choisissez qui peut l'utiliser.|Un bot partage qui agit toujours comme un role CMS specifique (par exemple, toujours editeur). Usted choisissez qui peut el utiliser.
Seuls les formulaires que vous énumérez ici peuvent être envoyés par un assistant IA — et seulement après confirmation du visiteur.|Seuls les formulaires que usted enumerez ici peuvent etre envoyes par un assistant IA — y seulement apres confirmation du visitiour.
Der gebündelte Site Web-Arbeitsbereich dieser Installation. CMS-Inhalte et Médias bleiben in Birkly et werden zur Laufzeit gelesen.|Der gebundelte Sitio Web-Arbeitsbereich dieser Installation. CMS-Inhalte y Medios bleiben in Birkly y werden zur Laufzeit gelesen.
Points de terminaison JSON en lecture seule disponibles après la publication. Utilisez view=summary, view=products ou view=claims.|Points de terminaison JSON en lecture seule disponibles apres la publicación. Utilisez view=summary, view=products o view=claims.
Rédigez le contenu du Guide de l'agent avec l'IA de vos sources déclarées, puis examinez chaque suggestion avant de l'enregistrer.|Redigez le contenido du Guía del agent con el IA de sus sources declarees, puis examinez chaque suggestion avant del guardar.
Facultatif : ajoutez des messages de positionnement clés — ce pour quoi vous êtes le plus connu et un facteur de différenciation.|Opcional : ajoutez des messages de positionnement cles — ce para quoi usted etes le plus connu y un facteur de differenciation.
Wie lange vorgerenderte Pages gecacht werden. <code>0</code> deaktiviert den Cache (jede Anfrage rendert neu). Max. 86400 (24 h).|Wie lange vorgerenderte Páginas gecacht werden. <code>0</code> deaktiviert den Cache (jede Anfrage rendert neu). Max. 86400 (24 h).
Utilisez le protocole SMTP de votre hébergeur, de Google Workspace, de Microsoft 365 ou de n'importe quel serveur de messagerie.|Utilisez le protocole SMTP de su hebergeur, de Google Workspace, de Microsoft 365 o de no importe quel serveur de messagerie.
Créez une clé API dans le tableau de bord Renvoyer. Votre adresse d'expéditeur doit utiliser un domaine que vous y avez vérifié.|Creez une cle API en le tableau de bord Renvoyer. Su adresse de expediteur doit utiliser un domaine que usted y avez verifie.
Choisissez au moins une source narrative — où les agents doivent lire la vérité sur vous (page À propos, page d'accueil, etc.).|Elija au moins une source narrative — ou les agents doivent lire la verite en usted (página À propos, página de accueil, etc.).
Dossier „{name}“ enthält Fichiers.

OK: Fichiers ins Stammverzeichnis verschieben. Annuler: Fichiers mit dem Dossier supprimer.|Carpeta „{name}“ enthält Archisus.

OK: Archisus ins Stammverzeichnis verschieben. Cancelar: Archisus mit dem Carpeta eliminar.
For Hub plugins and site packs, use the Hub tab. Free official plugins install with one click; paid plugins need a license key.|For Hub plugins and sitio packs, use the Hub tab. Free official plugins install with one click; paid plugins need a license key.
Vous n'êtes pas sûr ? Utilisez le journal uniquement sur votre ordinateur pour essayer des choses, ou Renvoyez / Cachet postal|Usted no etes pas sur ? Utilisez le journal uniquement en su ordinateur para essayer des choses, o Renvoyez / Cachet postal
Chaque utilisateur se connecte avec son propre rôle Birkly et ses propres autorisations. Une URL partagée pour toute l'équipe.|Chaque utilisateur se connecte con son propre role Birkly y ses propres autorisations. Une URL partagee para toute el equipe.
Pour envoyer les invitations par e-mail automatiquement, configurez d’abord une connexion e-mail dans Paramètres → Connexions.|Pour envoyer les invitations par e-mail automatiquement, configurez d'abord une connexion e-mail en Configuración → Conexións.
Pages canoniques, entrées CMS, listes Amazon, sites d'avis et documents. Résolu pour les URL publiques lors de la publication.|Páginas canoniques, entrees CMS, listes Amazon, sitios de avis y documents. Resolu para les URL publiques cuando de la publicación.
Erstellen Sie eine Collection mit Slug posts et aktivieren Sie öffentliches Lesen, damit die Lern-Site Entrées anzeigen kann.|Erstellen Sie eine Colección mit Slug posts y aktivieren Sie öffentliches Lesen, damit die Lern-Sitio Entradas anzeigen kann.
Discutez à l'intérieur de l'administrateur en utilisant une clé API de fournisseur sortant (OpenAI, Anthropic, Ollama, etc.).|Discutez a el interieur del administrateur en utilisant une cle API de fournisseur sortant (OpenAI, Anthropic, Ollama, etc.).
Pas encore de contexte médiatique. Choisissez des fichiers dans votre médiathèque et décrivez comment l'IA doit les utiliser.|Pas encore de contexte mediatique. Elija des archisus en su mediatheque y decrivez comment el IA doit les utiliser.
Gérez ici les utilisateurs et rôles admin CMS. Ouvrez la gestion des rôles ou vos paramètres de compte sur les pages dédiées.|Gerez ici les utilisateurs y roles admin CMS. Ouvrez la gestion des roles o sus parametres de compte en les páginas dediees.
Désactivé par défaut. Lorsqu'il est activé, n'importe qui peut soumettre cette collection sans licence de domaine ou clé API.|Desactivado par defaut. Lorsqu'il est active, no importe qui peut soumettre cette collection sin licence de domaine o cle API.
Par défautmäßig aus. Wenn aktiviert, darf das Birkly-Team Sie per E-mail kontaktieren—ohne Antwortpflicht et ohne Marketing.|Par defautmäßig aus. Wenn aktiviert, darf das Birkly-Team Sie per E-mail kontaktieren—ohne Antwortpflicht y ohne Marketing.
Vous modifiez un brouillon, puis publiez explicitement. Les points de terminaison publics ne servent que la version publiée.|Usted modifiez un brouillon, puis publiez explicitement. Les points de terminaison publics ne servent que la version publiee.
Êtes-vous sûr de vouloir supprimer cette configuration de suivi ? Cela arrêtera de collecter des analyses pour ce site Web.|¿Está seguro de vouloir eliminar cette configuration de suivi ? Cela arretera de collecter des analyses para ce sitio Web.
Choisissez une collection uniquement pour les sources générales ou sélectionnez une entrée spécifique pour une page ciblée.|Elija une collection uniquement para les sources generales o selectionnez une entree specifique para une página ciblee.
L'envoi de formulaire est activé mais aucun formulaire n'est encore autorisé — choisissez au moins un formulaire ci-dessus.|L'envoi de formulaire est active mais aucun formulaire no est encore autorise — choisissez au moins un formulaire ci-dessus.
Enregistrez les e-mails dans un fichier sur ce serveur — rien n'est envoyé. Idéal pour essayer Birkly sur votre ordinateur.|Enregistrez les e-mails en un archivo en ce serveur — rien no est envoye. Ideal para essayer Birkly en su ordinateur.
Build de préversion pouvant être instable. Confirmez que vous comprenez les risques avant d’installer ou de mettre à jour.|Build de preversion pouvant etre instable. Confirmez que usted comprenez les risques avant d'installer o de mettre a jour.
Open Router ou einen anderen Anbieter verbinden, damit der KI-Assistent kontextbezogen beim Erstellen et Modifier hilft.|Open Router o einen anderen Anbieter verbinden, damit der KI-Assistent kontextbezogen beim Erstellen y Editar hilft.
Cette mise à jour demande l’accès à votre site. Vérifiez les autorisations ci-dessous, puis acceptez pour mettre à jour.|Cette mise a jour demande l'acces a su sitio. Verifiez les autorisations ci-dessous, puis acceptez para mettre a jour.
Wählen Sie, wie dieser Hostname für die Site-Auslieferung genutzt wird. Apex- et www-Paare werden automatisch verknüpft.|Wählen Sie, wie dieser Hostname fur die Sitio-Auslieferung genutzt wird. Apex- y www-Paare werden automatisch verknupft.
Codes de vérification des moteurs de recherche et politique robots par défaut pour les pages sans réglage robots propre.|Codes de verification des moteurs de recherche y politique robots par defaut para les páginas sin reglage robots propre.
Supprime toutes les collections (y compris celles que vous avez créées), les entrées et le site public (dossier project)|Supprime toutes les collections (y compris celles que usted avez creees), les entrees y le sitio public (dossier project)
Tapez un code de langue (par exemple, en, de, fr) ou un nom pour voir les suggestions. Appuyez sur Entrée pour ajouter.|Tapez un code de langue (par exemple, en, de, fr) o un nom para voir les suggestions. Appuyez en Entrada para añadir.
Name, Beschreibung, Logo, Zeitzone et Sprache dieser Site Web. Die Site Web-Fichiers liegen unter Paramètres → Projet.|Name, Beschreibung, Logo, Zeitzone y Sprache dieser Sitio Web. Die Sitio Web-Archisus liegen unter Configuración → Projet.
Votre compte est prêt. Connectez l’IA dans Paramètres pour le chat admin ou l’accès MCP, ou explorez l’administration.|Su compte est pret. Connectez l'IA en Configuración para le chat admin o l'acces MCP, o explorez l'administration.
La clé API semble valide, mais le compte du fournisseur peut avoir besoin de crédits avant que le chat ne fonctionne.|La cle API semble valide, mais le compte du fournisseur peut avoir besoin de credits avant que le chat ne fonctionne.
Alle Fichiers aus diesem Site-Pack ersetzen? Zuerst wird ein Backup erstellt. Eigene Änderungen werden überschrieben.|Alle Archisus aus diesem Sitio-Pack ersetzen? Zuerst wird ein Backup erstellt. Eigene Änderungen werden uberschrieben.
Cliquez sur Connexion rapide pour ajouter votre fournisseur d'IA et votre clé API pour le chat dans l'administration.|Haga clic en Conexión rapide para añadir su fournisseur de IA y su cle API para le chat en el administration.
Suivez les liens d’invitation et les e-mails. Les invitations en attente peuvent être copiées, renvoyées ou annulées.|Suivez les liens d'invitation y les e-mails. Les invitations en attente peuvent etre copiees, renvoyees o annulees.
Aucune geschützten Ressourcen. Alle Collectionen sind für Plugins zugänglich (vorbehaltlich Autorisationsprüfungen).|Ninguna geschutzten Ressourcen. Alle Colecciónen sind fur Plugins zugänglich (vorbehaltlich Permisosprufungen).
Affichez le flux d'activité et le journal d'audit détaillé sur des pages dédiées avec des filtres et une pagination.|Affichez le flux de activite y le journal de audit detaille en des páginas dediees con des filtres y une pagination.
Les modèles sont chargés en direct à partir de votre fournisseur. Recherchez dans la liste ou tapez un ID de modèle.|Les modeles sont charges en direct a partir de su fournisseur. Recherchez en la liste o tapez un ID de modele.
Changez de modèle à partir du menu Sparkle dans le compositeur pour des réponses plus rapides ou plus intelligentes.|Changez de modele a partir du menu Sparkle en le compositiour para des reponses plus rapides o plus intelligentes.
Utilisez la fonction de messagerie intégrée au serveur. Souvent peu fiable, ne l'utilisez que si votre hôte l'exige.|Utilisez la fonction de messagerie integree au serveur. Souvent peu fiable, ne el utilisez que si su hote el exige.
Aktuelle Projetdatei-Änderungen, Paramètresupdates et Git-Ereignisse — mit Angabe, wer die Änderung vorgenommen hat.|Aktuelle Projetdatei-Änderungen, Configuraciónupdates y Git-Ereignisse — mit Angabe, wer die Änderung vorgenommen hat.
Informations de connexion enregistrées pour les plugins qui appellent des API externes (expédition, CRM, webhooks).|Informations de connexion enregistrees para les plugins qui appellent des API externes (expedition, CRM, webhooks).
Exécutez une action ci-dessus — des suggestions s'affichent ici pour que vous puissiez les accepter ou les refuser.|Executez une action ci-dessus — des suggestions se affichent ici para que usted puissiez les accepter o les refuser.
Site-weites Limit für KI-Tools. Überschreibt Freigaben pro Connexion — Tools werden aus MCP et Admin-Chat entfernt.|Sitio-weites Limit fur KI-Tools. Überschreibt Freigaben pro Conexión — Tools werden aus MCP y Admin-Chat entfernt.
Aucun fichier page autonome dans project/ — seuls des modèles répétitifs ou des fichiers internes ont été détectés.|Ninguno archivo página autonome en project/ — seuls des modeles repetitifs o des archisus internes ont ete detectes.
Site-Pack-ZIP installieren (manifest.json + collections/ + project/). Nutzt dieselbe Engine wie Hub-Installationen.|Sitio-Pack-ZIP installieren (manifest.json + collections/ + project/). Nutzt dieselbe Engine wie Hub-Installationen.
Cette sous-collection appartient à la collection parent ci-dessus et sera attachée aux entrées de cette collection.|Cette sous-collection appartient a la collection parent ci-dessus y sera attachee aux entrees de cette collection.
Envoi via {provider} depuis {from_email}. Les notifications de formulaire et les plugins utilisent cette connexion.|Envoi via {provider} desde {from_email}. Les notifications de formulaire y les plugins utilisent cette connexion.
Öffnet die Projetwebsite in einem neuen Tab. Wählen Sie unten den URL-Stil, der auf Ihrem Host besser funktioniert.|Öffnet die Projetwebsitio in einem neuen Tab. Wählen Sie unten den URL-Stil, der auf Ihrem Host besser funktioniert.
Copiez l'URL du connecteur et collez-la dans votre application d'IA. Laissez l'ID client et le secret client vides.|Copiez el URL du connecteur y collez-la en su application de IA. Laissez el ID client y le secret client vides.
<x id="1666"/><g id="1669">Saisissez le code à 6 chiffres de votre application d'authentification<x id="1674"/></g>|<x id="1666"/><g id="1669">Introduzca le code a 6 chiffres de su application de authentification<x id="1674"/></g>
Chemin URL où le fichier JSON du Guide de l'agent principal est servi (par défaut : /.well-known/agent-guide.json).|Chemin URL ou le archivo JSON du Guía del agent principal est servi (par defaut : /.well-known/agent-guide.json).
Préversion alpha. Les builds peuvent être instables ou incomplets. Installez uniquement si vous acceptez ce risque.|Preversion alpha. Les builds peuvent etre instables o incomplets. Installez uniquement si usted acceptez ce risque.
Recevez un e-mail ou un webhook lorsque le point de contrôle de santé du CMS signale une erreur ou un état dégradé.|Recevez un e-mail o un webhook cuando le point de controle de sante du CMS signale une erreur o un etat degrade.
Bewertungen gelten für diese Birkly-Installation. Nur Nutzer, die das Plugin hier installé haben, können bewerten.|Bewertungen gelten fur diese Birkly-Installation. Nur Nutzer, die das Plugin hier installe haben, können bewerten.
Rien n’est listé pour le moment — utilisez « Configurer avec l’IA » ou « Actualiser depuis les fichiers du site ».|Rien n'est liste para le moment — utilisez « Configurer con l'IA » o « Actualiser desde les archisus du sitio ».
Cette extension demande l’accès à votre site. Vérifiez les autorisations ci-dessous, puis acceptez pour installer.|Cette extension demande l'acces a su sitio. Verifiez les autorisations ci-dessous, puis acceptez para installer.
Optionale Beispielkollektionen et Demo-Site Web. Leer starten ist möglich. Marsk installé eine Demo-Journal-Site.|Optionale Beispielkollektionen y Demo-Sitio Web. Leer starten ist möglich. Marsk installe eine Demo-Registro-Sitio.
Les collections regroupent vos publications, produits et autres contenus. Créez-en une, puis ajoutez des entrées.|Les collections regroupent sus publicacións, produits y autres contenidos. Creez-en une, puis ajoutez des entrees.
Licence auf dieser Site Web widerrufen. Sie können den Schlüssel jetzt auf einer anderen Installation verwenden.|Licence auf dieser Sitio Web widerrufen. Sie können den Schlussel jetzt auf einer anderen Installation verwenden.
Aucune clé API requise. Assurez-vous qu'Ollama est en cours d'exécution (par défaut : http ://localhost :11434).|Ninguna cle API requise. Assurez-usted que Ollama est en cours de execution (par defaut : http ://localhost :11434).
Aucune source CMS liée pour le moment. Ajoutez des sources dans l'onglet Sources pour suivre la synchronisation.|Ninguna source CMS liee para le moment. Ajoutez des sources en el onglet Sources para suivre la synchronisation.
Les extensions installées sont résumées ici. Installez, activez et configurez les extensions sur la page dédiée.|Les extensions installees sont resumees ici. Installez, activez y configurez les extensions en la página dediee.
Sélectionnez une connexion IA pour commencer à discuter. L'IA peut vous aider à créer et à modifier du contenu.|Seleccione une connexion IA para commencer a discuter. L'IA peut usted aider a crear y a editar du contenido.
Ajoutez du texte de guide d'agent analysable sur n'importe quelle page de projet — pas injecté automatiquement.|Ajoutez du texte de guide de agent analysable en no importe quelle página de projet — pas injecte automatiquement.
Annuler la publication du Guide de l'agent ? Les points de terminaison publics cesseront de servir votre guide.|Cancelar la publicación du Guía del agent ? Les points de terminaison publics cesseront de servir su guide.
Faits officiels sur la marque pour les IA de chat et de recherche — affirmations, produits et réponses étayées.|Faits officiels en la marque para les IA de chat y de recherche — affirmations, produits y reponses etayees.
Voici vos nouveaux codes de sauvegarde. Rangez-les dans un endroit sûr. Chaque code peut être utilisé une fois.|Voici sus nuevox codes de sauvegarde. Rangez-les en un endroit sur. Chaque code peut etre utilise une fois.
Gesteuert unter Paramètres → Site delivery. SSR ist nur verfügbar, wenn der Modus „In Birkly (project/)“ ist. |Gesteuert unter Configuración → Sitio delivery. SSR ist nur verfugbar, wenn der Modus „In Birkly (project/)“ ist. 
Développez en local : IA desktop via agent de service + mcp-remote. Pas besoin d’exposer l’admin sur Internet.|Developpez en local : IA desktop via agent de service + mcp-remote. Pas besoin d'exposer l'admin en Internet.
Connectez admin chat AI ou configurez l'accès MCP pour des outils externes tels que Claude Desktop et Cursor.|Connectez admin chat AI o configurez el acces MCP para des outils externes tels que Claude Desktop y Cursor.
Par défautmäßig aus. Schemas et project-HTML/CSS werden immer installé; Beispieleinträge nur bei Actifierung.|Par defautmäßig aus. Schemas y project-HTML/CSS werden immer installe; Beispieleinträge nur bei Activoierung.
Connexions AI partagées avec un rôle CMS fixe. Seuls les administrateurs peuvent créer des agents de service.|Conexións AI partagees con un role CMS fixe. Seuls les administrateurs peuvent crear des agents de service.
Actifieren Sie oben einen Licenceschlüssel ou installieren Sie ein kostenpflichtiges Angebot unter Entdecken.|Activoieren Sie oben einen Licenceschlussel o installieren Sie ein kostenpflichtiges Angebot unter Entdecken.
Comment souhaitez-vous que Birkly envoie des e-mails ? Choisissez l'option qui correspond à votre situation.|Comment souhaitez-usted que Birkly envoie des e-mails ? Elija el option qui correspond a su situation.
Le collage d'image n'est pas pris en charge pour cette connexion. Utilisez un fournisseur capable de vision.|Le collage de image no est pas pris en charge para cette connexion. Utilisez un fournisseur capable de vision.
Un aperçu en langage clair des actions que l'assistant IA d'un visiteur verrait avec vos paramètres actuels.|Un apercu en langage clair des actions que el assistant IA de un visitiour verrait con sus parametres actuels.
Publier un Guide de l'agent faisant autorité pour les systèmes et les agents d'IA à découvrir et à utiliser.|Publicar un Guía del agent faisant autorite para les systemes y les agents de IA a decouvrir y a utiliser.
Markenfakten et Discovery-Oberflächen veröffentlichen, damit Such- et Chat-KIs Ihre Site korrekt darstellen.|Markenfakten y Discovery-Oberflächen veröffentlichen, damit Such- y Chat-KIs Ihre Sitio korrekt darstellen.
Tarif limité par le prestataire. Attendez un moment et réessayez, ou vérifiez les limites de votre plan API.|Tarif limite par le prestataire. Attendez un moment y reessayez, o verifiez les limites de su plan API.
Après la publication, vous obtenez un écran de réussite avec votre URL en direct et votre numéro de version.|Apres la publicación, usted obtenez un ecran de reussitio con su URL en direct y su numero de version.
Choisissez comment les visiteurs accèdent à votre site — aperçu local, sous-domaine ou domaine personnalisé.|Elija comment les visitiours accedent a su sitio — apercu local, sous-domaine o domaine personnalise.
Optional: Legen Sie fest, wo CMS et öffentliche Site Web erreichbar sind. Später in den Paramètres änderbar.|Optional: Legen Sie fest, wo CMS y öffentliche Sitio Web erreichbar sind. Später in den Configuración änderbar.
Tunnel — cloudflared ou ngrok vers votre port local, puis https://VOTRE-TUNNEL/mcp comme URL de connecteur.|Tunnel — cloudflared o ngrok hacia su port local, puis https://VOTRE-TUNNEL/mcp comme URL de connecteur.
Voulez-vous vraiment restaurer cette version ? La version actuelle sera enregistrée comme nouvelle version.|Voulez-usted vraiment restaurer cette version ? La version actuelle sera enregistree comme nueva version.
Créez un agent de service partagé avec un rôle CMS fixe. Choisissez qui peut le voir et se connecter à lui.|Creez un agent de service partage con un role CMS fixe. Elija qui peut le voir y se connecter a lui.
Supprimer cet agent de service ? Votre application d'IA n'accédera plus à votre site par son intermédiaire.|Eliminar cet agent de service ? Su application de IA no accedera plus a su sitio par son intermediaire.
Rezensionen gelten für diese Birkly-Installation. Nur wer das Plugin hier installé et nutzt, kann bewerten.|Rezensionen gelten fur diese Birkly-Installation. Nur wer das Plugin hier installe y nutzt, kann bewerten.
Ajoute un petit lien de pied de page à /for-agents lorsque le pied de page est détecté (activé par défaut).|Ajoute un petit lien de pied de página a /for-agents cuando le pied de página est detecte (active par defaut).
Par défautsprache für öffentliche Site Web-Inhalte et Storefront-Vorlagen. Ändert pas die Admin-Oberfläche.|Par defautsprache fur öffentliche Sitio Web-Inhalte y Storefront-Vorlagen. Ändert pas die Admin-Oberfläche.
Aucun produit ou service pour le moment. Ajoutez-en un ou utilisez AI Assist pour générer des suggestions.|Ninguno produit o service para le moment. Ajoutez-en un o utilisez AI Assist para generer des suggestions.
Richten Sie unter Paramètres eine KI-Connexion ein, damit der Assistent kontextbezogene Hilfe bieten kann.|Richten Sie unter Configuración eine KI-Conexión ein, damit der Assistent kontextbezogene Hilfe bieten kann.
Alle aktiven KI-App-Connexions für {who} widerrufen? Erneute Anmeldung in Claude, Cursor ou ChatGPT nötig.|Alle aktiven KI-App-Conexións fur {who} widerrufen? Erneute Anmeldung in Claude, Cursor o ChatGPT nötig.
Facultatif. Alerte en texte brut envoyée par courrier du serveur lorsque la santé est erronée ou dégradée.|Opcional. Alerte en texte brut envoyee par courrier du serveur cuando la sante est erronee o degradee.
API de messagerie moderne — inscrivez-vous sur resend.com, vérifiez votre domaine et collez votre clé API.|API de messagerie moderne — inscrivez-usted en resend.com, verifiez su domaine y collez su cle API.
Aidez-moi à rédiger cette nouvelle entrée. Demandez quel sujet je veux, puis suggérez du contenu de champ.|Ayudaz-moi a rediger cette nueva entree. Demandez quel sujet je veux, puis suggerez du contenido de champ.
Aucune invitation pour le moment. Utilisez Inviter un utilisateur pour envoyer un e-mail ou créer un lien.|Ninguna invitation para le moment. Utilisez Inviter un utilisateur para envoyer un e-mail o crear un lien.
Vous avez déjà une connexion pour ce fournisseur. Modifiez la connexion existante ou supprimez-la d'abord.|Usted avez deja une connexion para ce fournisseur. Modifiez la connexion existante o supprimez-la de abord.
Maintenez la touche Ctrl/Cmd enfoncée pour sélectionner plusieurs collections (À propos, Produits, etc.).|Maintenez la touche Ctrl/Cmd enfoncee para selectionner plusieurs collections (À propos, Productos, etc.).
Copiez et collez ce code dans votre site Web pour créer un formulaire qui sera soumis à cette collection.|Copiez y collez ce code en su sitio Web para crear un formulaire qui sera soumis a cette collection.
<x id="2166"/><g id="2169">Scannez ce code QR avec votre application d'authentification<x id="2174"/></g>|<x id="2166"/><g id="2169">Scannez ce code QR con su application de authentification<x id="2174"/></g>
Laissez vide pour générer automatiquement. Les plugins l'utilisent pour choisir une connexion spécifique.|Laissez vide para generer automatiquement. Les plugins el utilisent para choisir une connexion specifique.
Hub-Dienst pas erreichbar. Die Installation läuft möglicherweise noch — kurz warten et erneut versuchen.|Hub-Dienst pas erreichbar. Die Installation läuft möglicherweise noch — kurz warten y erneut versuchen.
Noch aucune Delivery-Hostnamen. Domaine registrieren ou Hosting-URLs im Assistenten unten konfigurieren.|Noch aucune Delivery-Hostnamen. Domaine registrieren o Hosting-URLs im Assistenten unten konfigurieren.
Après la publication : les agents voient votre nom et votre résumé dans le Guide public des agents JSON.|Apres la publicación : les agents voient su nom y su resume en le Guía public des agents JSON.
Bilder et Fichiers in der Bibliothèque média enregistrer et in Entréesfeldern sowie Vorlagen verwenden.|Bilder y Archisus in der Bibliotheque media guardar y in Entradasfeldern sowie Vorlagen verwenden.
Alle Admin-Utilisateur müssen 2FA einrichten. Ohne 2FA gilt eine konfigurierbare Karenzzeit vor Sperre.|Alle Admin-Usuario mussen 2FA einrichten. Ohne 2FA gilt eine konfigurierbare Karenzzeit vor Sperre.
Sélectionnez des entrées pour les modifier, publier ou mettre à jour en masse depuis la barre d’outils.|Seleccione des entrees para les editar, publicar o mettre a jour en masse desde la barre d'outils.
Les modèles Birkly utilisent {pour chaque entrée de la 'collection'} syntaxe pour les pages dynamiques.|Les modeles Birkly utilisent {pour chaque entree de la 'collection'} syntaxe para les páginas dynamiques.
Avancé : mcp-bridge (clé API) sous Developer / debug — legacy ; préférez agent de service + mcp-remote.|Avance : mcp-bridge (cle API) sous Developer / debug — legacy ; preferez agent de service + mcp-remote.
Ouvrir Sie die Lern-Site et prüfen Sie, ob der publiée Entrée erscheint (nach dem Publier neu charger).|Abrir Sie die Lern-Sitio y prufen Sie, ob der publiee Entrada erscheint (nach dem Publicar neu charger).
Utilisez l'onglet Approbations pour examiner les modifications proposées par l'IA avant leur exécution.|Utilisez el onglet Approbations para examiner les modifications proposees par el IA avant leur execution.
Identité et positionnement du profil. Les résumés ici doivent refléter vos sources de vérité déclarées.|Identite y positionnement du profil. Les resumes ici doivent refleter sus sources de verite declarees.
Aucune approbation en attente. Lorsque l'IA souhaite modifier le contenu, les demandes s'affichent ici.|Ninguna approbation en attente. Lorsque el IA souhaite editar le contenido, les demandes se affichent ici.
Stoppt die Puls-Animation am Guide-Button, wenn Sie einen neuen Admin-Bereich zum ersten Mal besuchen.|Stoppt die Puls-Animation am Guía-Button, wenn Sie einen neuen Admin-Bereich zum ersten Mal besuchen.
Règles d'autorisation explicites pour GPTBot, PerplexityBot, ClaudeBot et autres sur les URL du guide.|Regles de autorisation explicites para GPTBot, PerplexityBot, ClaudeBot y autres en les URL du guide.
Unter Paramètres → KI eine Connexion hinzufügen, damit der Assistent beim Erstellen et Modifier hilft.|Unter Configuración → KI eine Conexión hinzufugen, damit der Assistent beim Erstellen y Editar hilft.
Dans le cachet de la poste, ouvrez les jetons → API de votre serveur → copiez le jeton API du serveur.|Dans le cachet de la poste, ouvrez les jetons → API de su serveur → copiez le jeton API du serveur.
Utilise votre brouillon et le contenu du CMS lié. Idéal lorsque vous avez déjà rempli certains champs.|Utilise su brouillon y le contenido du CMS lie. Ideal cuando usted avez deja rempli certains champs.
Demandez à un administrateur de vous affecter un agent de service ou utilisez le connecteur personnel.|Demandez a un administrateur de usted affecter un agent de service o utilisez le connecteur personnel.
Optional: Paramètres → Projet öffnen et project/index.html ansehen — die Lern-Vorlage lädt Ihre posts.|Optional: Configuración → Projet öffnen y project/index.html ansehen — die Lern-Vorlage lädt Ihre posts.
Steuern Sie Aufmerksamkeitsanimationen et setzen Sie Ihren Guide-Fortschritt für dieses Konto zurück.|Steuern Sie Aufmerksamkeitsanimationen y setzen Sie Ihren Guía-Fortschritt fur dieses Konto zuruck.
Choisissez des collections spécifiques ou exécutez des améliorations ciblées sur le brouillon actuel.|Elija des collections specifiques o executez des ameliorations ciblees en le brouillon actuel.
Demandez à l'IA de créer ou de modifier du contenu… Collez ou déposez des images et des fichiers ici.|Demandez a el IA de crear o de editar du contenido… Collez o deposez des images y des archisus ici.
Cette URL utilise HTTP. Claude le rejettera — ouvrez Birkly avec https :// et copiez à nouveau l'URL.|Cette URL utilise HTTP. Claude le rejettera — ouvrez Birkly con https :// y copiez a nuevo el URL.
Les valeurs par défaut du site ci-dessus s’appliquent lorsqu’une fiche n’a pas de titre personnalisé.|Les valeurs par defaut du sitio ci-dessus s'appliquent lorsqu'une fiche n'a pas de titre personnalise.
Utilisez Chrome 146+ et ouvrez chrome ://flags/# enable-webmcp-testingpour activer les tests WebMCP.|Utilisez Chrome 146+ y ouvrez chrome ://flags/# enable-webmcp-testingpour activer les tests WebMCP.
Le catalogue d’outils et la finition de l’interface de liste blanche arrivent dans le jalon WebMCP.|Le catalogue d'outils y la finition de l'interface de liste blanche arrivent en le jalon WebMCP.
Les réclamations prises en charge apparaissent dans la vue récapitulative publique pour les agents.|Les reclamations prises en charge apparaissent en la vue recapitulative publique para les agents.
Sélectionnez une connexion AI pour cette action, ou utilisez Fill gaps pour des suggestions de base|Seleccione une connexion AI para cette action, o utilisez Fill gaps para des suggestions de base
Les applications d'IA se connectent à Birkly à cette adresse — jamais sur l'hôte de votre site Web.|Les applications de IA se connectent a Birkly a cette adresse — jamais en el hote de su sitio Web.
Votre collection est prête. Ajoutez votre première entrée ou retournez à la liste des collections.|Su collection est prete. Ajoutez su premiere entree o retournez a la liste des collections.
Liste de contrôle pour la découverte technique et via la recherche — à exécuter après publication.|Liste de controle para la decouverte technique y via la recherche — a executer apres publicación.
Restaurer la version {version} ? Votre brouillon actuel sera d’abord enregistré dans l’historique.|Restaurar la version {version} ? Su brouillon actuel sera d'abord enregistre en l'historique.
E-mail transactionnel fiable — collez votre jeton d'API de serveur à partir du cachet de la poste.|E-mail transactionnel fiable — collez su jeton de API de serveur a partir du cachet de la poste.
Utilisez ce script en développement sur localhost. Le suivi analytique est inclus automatiquement.|Utilisez ce script en developpement en localhost. Le suivi analytique est inclus automatiquement.
Installer Sie User, Commerce ou Automation, um Ihre Site zu erweitern. Continuere Plugins folgen.|Installer Sie User, Commerce o Automation, um Ihre Sitio zu erweitern. Continuare Plugins folgen.
Utilisez la checklist du Guide pour publier votre premier élément et explorer les extensions Hub.|Utilisez la checklist du Guía para publicar su premier element y explorer les extensions Hub.
CMS-Utilisateur verwalten hier das Admin-Panel. Site-Besucher et Mitglieder werden separat unter |CMS-Usuario verwalten hier das Admin-Panel. Sitio-Besucher y Mitglieder werden separat unter 
L'IA peut lire et modifier les fichiers de votre projet/ dossier pour les constructions de site.|L'IA peut lire y editar les archisus de su projet/ dossier para les constructions de sitio.
Browse the Hub tab to install official plugins like Commerce and Events — free for Birkly sites.|Browse the Hub tab to install official plugins like Commerce and Events — free for Birkly sitios.
Partagez ce lien avec l’utilisateur. Il pourra définir son mot de passe et rejoindre votre site.|Partagez ce lien con l'utilisateur. Il pourra definir son mot de passe y rejoindre su sitio.
Répertorie les pages de projet et /for-agents dans /sitemap.xml ; ajoute Sitemap : à robots.txt.|Repertorie les páginas de projet y /for-agents en /sitiomap.xml ; ajoute Sitiomap : a robots.txt.
Ces messages aident les agents à répondre à la question « qu'est-ce qui vous rend différent ? »|Ces messages aident les agents a repondre a la question « qu'est-ce qui usted rend different ? »
Êtes-vous sûr de vouloir supprimer ce groupe ? Les collections seront déplacées vers ungrouped.|¿Está seguro de vouloir eliminar ce groupe ? Les collections seront deplacees hacia ungrouped.
Les agents doivent d'abord lire ces sources. Les résumés dans d'autres onglets en sont dérivés.|Les agents doivent de abord lire ces sources. Les resumes en de autres onglets en sont derives.
Aufbewahrungstage et Einwilligungstext gelten bei den Voreinstellungen Par défaut ou Erweitert.|Aufbewahrungstage y Einwilligungstext gelten bei den Voreinstellungen Par defaut o Erweitert.
Aucune proposition générée — essayez de combler les lacunes ou ajoutez d'abord plus de contexte|Ninguna proposition generee — essayez de combler les lacunes o ajoutez de abord plus de contexte
Conservez ces codes de sauvegarde dans un endroit sûr. Chaque code peut être utilisé une fois.|Conservez ces codes de sauvegarde en un endroit sur. Chaque code peut etre utilise une fois.
Nutzen Sie Checkliste et Journeys, um ersten Inhalt zu veröffentlichen et den Hub zu erkunden.|Nutzen Sie Checkliste y Journeys, um ersten Inhalt zu veröffentlichen y den Hub zu erkunden.
Hier einen Anbieter verbinden, um KI-Chat im Admin et intelligentere Guide-Hinweise zu nutzen.|Hier einen Anbieter verbinden, um KI-Chat im Admin y intelligentere Guía-Hinweise zu nutzen.
Aucune suggestion — essayez de lier le contenu du CMS ou d'exécuter le remplissage automatique|Ninguna suggestion — essayez de lier le contenido du CMS o de executer le remplissage automatique
Suggérer 5 nouvelles idées d'entrée pour cette collection avec des titres et de brefs aperçus.|Suggerer 5 nuevas idees de entree para cette collection con des titres y de brefs apercus.
connexion e-mail pour les notifications de formulaire, le commerce et d'autres fonctionnalités|connexion e-mail para les notifications de formulaire, le commerce y de autres fonctionnalites
En un clic : lisez vos sources déclarées et proposez une ébauche complète du Guide de l'agent.|En un clic : lisez sus sources declarees y proposez une ebauche complete du Guía del agent.
Aucune entrée de journal pour le moment. Les actions échouées et les erreurs apparaissent ici.|Ninguna entree de journal para le moment. Les actions echouees y les erreurs apparaissent ici.
Entrées ou collections CMS de référence — le contenu reste dans le contenu, pas dupliqué ici.|Entradas o collections CMS de reference — le contenido reste en le contenido, pas duplique ici.
Attention : __ BIRKLYPH0__ action(s) échouée (s). Voir l'onglet Console pour plus de détails.|Attention : __ BIRKLYPH0__ action(s) echouee (s). Voir el onglet Console para plus de details.
Ihr Entrée wurde enregistré. Liste anzeigen, weiteren Entrée hinzufügen ou weiter bearbeiten.|Ihr Entrada wurde enregistre. Liste anzeigen, weiteren Entrada hinzufugen o weiter bearbeiten.
Vous pouvez joindre des images et des documents aux messages de discussion pour le contexte.|Usted pouvez joindre des images y des documents aux messages de discussion para le contexte.
Phrase explorable facultative dans le pied de page avec un lien vers votre Guide de l'agent.|Phrase explorable facultative en le pied de página con un lien hacia su Guía del agent.
Cliquez sur Configurer l'e-mail ci-dessus et suivez les étapes. Cela ne prend qu'une minute.|Haga clic en Configurer el e-mail ci-dessus y suivez les pasos. Cela ne prend que une minute.
Saviez-vous que Birkly peut fonctionner avec MySQL ou PostgreSQL au lieu de fichiers plats ?|Saviez-usted que Birkly peut fonctionner con MySQL o PostgreSQL au lieu de archisus plats ?
L’éditeur complet migrera dans cet onglet au prochain jalon. Vos données restent inchangées.|L'editeur complet migrera en cet onglet au prochain jalon. Sus donnees restent inchangees.
Collectionen definieren Ihre Inhaltstypen. Entrées sind die publiéen Inhalte auf Ihrer Site.|Colecciónen definieren Ihre Inhaltstypen. Entradas sind die publieen Inhalte auf Ihrer Sitio.
Déclarations vérifiables. Joignez des preuves avant de marquer une réclamation comme étayée.|Declarations verifiables. Joignez des preuves avant de marquer une reclamation comme etayee.
Passez en revue chaque suggestion — rien n'est enregistré tant que vous n'avez pas accepté.|Passez en revue chaque suggestion — rien no est enregistre tant que usted no avez pas accepte.
Vous pouvez modifier la valeur par défaut ultérieurement à partir de la liste de connexion.|Usted pouvez editar la valeur par defaut ulterieurement a partir de la liste de connexion.
HTML, CSS, JS et Assets unter <code>project/</code> durchsuchen, bearbeiten et hochcharger.|HTML, CSS, JS y Assets unter <code>project/</code> durchsuchen, bearbeiten y hochcharger.
La publication publique est désactivée — activez-la avec le commutateur en haut de la page.|La publicación publique est desactivee — activez-la con le commutateur en haut de la página.
Projeteinstellungen für Site-Fichiers, Vorlagen et die Struktur Ihres öffentlichen Projets.|Projeteinstellungen fur Sitio-Archisus, Vorlagen y die Struktur Ihres öffentlichen Projets.
Titre, description et image sociale de secours. Chaque fiche peut les remplacer ci-dessous.|Título, description y image sociale de secours. Chaque fiche peut les remplacer ci-dessous.
Ou téléchargez le fichier guide et téléchargez-le dans le dossier/.well-known/de votre hôte|Ou telechargez le archivo guide y telechargez-le en le dossier/.well-known/de su hote
Ersetzen et Zusammenführen erstellen unter backup/ ein Backup, wenn Inhalte vorhanden sind.|Ersetzen y Zusammenfuhren erstellen unter backup/ ein Backup, wenn Inhalte vorhanden sind.
Masqué par votre filtre de canal Discover. Passez sur {required} pour afficher cette offre.|Masque par su filtre de canal Discover. Passez en {required} para afficher cette offre.
Consultez les versions enregistrées et restaurez une révision plus ancienne si nécessaire.|Consultez les versions enregistrees y restaurez une revision plus ancienne si necessaire.
Chargement Sie Bilder et Fichiers hoch et verwenden Sie sie in Entréesfeldern et Vorlagen.|Cargando Sie Bilder y Archisus hoch y verwenden Sie sie in Entradasfeldern y Vorlagen.
Fermer la configuration ? Les modifications non enregistrées à cette étape seront perdues.|Cerrar la configuration ? Les modifications non enregistrees a cette paso seront perdues.
Le serveur a renvoyé une réponse non valide. Vérifiez que vous êtes connecté et réessayez.|Le serveur a renvoye une reponse non valide. Verifiez que usted etes connecte y reessayez.
Facultatif. PUBLICATION JSON avec rapport d'état lorsque le statut est erroné ou dégradé.|Opcional. PUBLICATION JSON con rapport de etat cuando le statut est errone o degrade.
Abgewählte Plugins sind in der Pagesleiste ausgeblendet et deren Admin-Bereiche gesperrt.|Abgewählte Plugins sind in der Páginasleiste ausgeblendet y deren Admin-Bereiche gesperrt.
Assistant intégré/ Ollama sur localhost — OK (BYOAI sortant ou http ://localhost :11434).|Assistant integre/ Ollama en localhost — OK (BYOAI sortant o http ://localhost :11434).
Änderungen hier werden automatisch enregistré — aucun separater Enregistrer-Button nötig.|Änderungen hier werden automatisch enregistre — aucun separater Guardar-Button nötig.
Aucune Domainees registriert — Ratenlimits pro Domaine sind pas konfiguriert. Domaine im |Ninguna Domainees registriert — Ratenlimits pro Domaine sind pas konfiguriert. Domaine im 
Marke et Discovery-Oberflächen für Besucher et KI — Agent Guide, SEO et verwandte Tools.|Marke y Discovery-Oberflächen fur Besucher y KI — Agent Guía, SEO y verwandte Tools.
Discutez avec l'IA à l'intérieur de Birkly en utilisant la clé API de votre fournisseur.|Discutez con el IA a el interieur de Birkly en utilisant la cle API de su fournisseur.
Diese Autorisation ist gesperrt. Zahlung aktualisieren ou den Hub-Anbieter kontaktieren.|Diese Autorisation ist gesperrt. Zahlung aktualisieren o den Hub-Anbieter kontaktieren.
Besucherstatistik für diese Domaine. Self-hosted Analytique ohne externe Abhängigkeiten.|Besucherstatistik fur diese Domaine. Self-hosted Analytique ohne externe Abhängigkeiten.
Kombinierte Übersicht über alle aktivierten Domainees. Tiefe folgt Ihrer Voreinstellung.|Kombinierte Übersicht uber alle aktivierten Domainees. Tiefe folgt Ihrer Voreinstellung.
Lorsqu'elles sont activées, les entrées peuvent avoir un contenu dans plusieurs langues|Lorsqu'elles sont activees, les entrees peuvent avoir un contenido en plusieurs langues
Suggérez un titre plus clair et plus convivial pour le référencement pour cette entrée.|Suggerez un titre plus clair y plus convivial para le referencement para cette entree.
Plugin-Anfrage échec (ungültige Serverantwort). Erneut versuchen ou Server-Logs prüfen.|Plugin-Anfrage echec (ungultige Serverantwort). Erneut versuchen o Server-Logs prufen.
Connectez les applications d'IA à Birkly ou discutez à l'intérieur de l'administrateur.|Connectez les applications de IA a Birkly o discutez a el interieur del administrateur.
Passez en revue les différences entre les entrées CMS liées et les champs de brouillon.|Passez en revue les differences entre les entrees CMS liees y les champs de brouillon.
Si les assistants IA peuvent agir sur votre site en direct. Gérer dans l'onglet Outils.|Si les assistants IA peuvent agir en su sitio en direct. Gerer en el onglet Outils.
Les collections et les entrées vivent sous le contenu/ — sauvegardez-les régulièrement.|Les collections y les entrees vivent sous le contenido/ — sauvegardez-les regulierement.
Votre connexion IA précédente a été supprimée. Choisissez une connexion pour continuer.|Su connexion IA precedente a ete supprimee. Elija une connexion para continuer.
Oben eine Voreinstellung wählen et enregistrer, dann die Site Web für Berichte öffnen.|Oben eine Voreinstellung wählen y guardar, dann die Sitio Web fur Berichte öffnen.
Plugin-Admin-Bereiche sperren (z. B. Commerce), um Pagesleiste et Hooks zu blockieren.|Plugin-Admin-Bereiche sperren (z. B. Commerce), um Páginasleiste y Hooks zu blockieren.
Impossible d’afficher l’historique : informations d’entrée ou de collection manquantes|Impossible d'afficher l'historique : informations d'entree o de collection manquantes
Fichier non pris en charge : __BIRKLYPH0 __. Coller des images ou des documents texte.|Archivo non pris en charge : __BIRKLYPH0 __. Coller des images o des documents texte.
Le connecteur personnel est désactivé. Seuls les administrateurs peuvent le réactiver.|Le connecteur personnel est desactive. Seuls les administrateurs peuvent le reactiver.
Pour le développement local uniquement. Non nécessaire pour une configuration normale.|Pour le developpement local uniquement. No necessaire para une configuration normale.
Les liens JSON publiés renvoient à ces sources afin que les agents puissent les citer.|Les liens JSON publies renvoient a ces sources afin que les agents puissent les citer.
Konfigurieren Sie, wo das CMS-Admin-Panel et die öffentliche Site Web erreichbar sind.|Konfigurieren Sie, wo das CMS-Admin-Panel y die öffentliche Sitio Web erreichbar sind.
Résumez les entrées de cette collection. Mettre en évidence les thèmes et les lacunes.|Resumez les entrees de cette collection. Mettre en evidence les themes y les lacunes.
Pas encore publié. Activez la publication et utilisez Publier lorsque vous êtes prêt.|Pas encore publie. Activez la publicación y utilisez Publicar cuando usted etes pret.
Les réponses utilisent uniquement l'instantané publié, avec les sources répertoriées.|Les reponses utilisent uniquement el instantane publie, con les sources repertoriees.
Setup-Checkliste abgeschlossen. Ouvrir Sie den Guide jederzeit für Journeys et Hilfe.|Setup-Checkliste abgeschlossen. Abrir Sie den Guía jederzeit fur Journeys y Hilfe.
Images ou vidéos à partir d'un média, ou d'une URL de média externe (image ou vidéo).|Images o videos a partir de un media, o de une URL de media externe (image o video).
Erstellen Sie den ersten Entrée, um Inhalte aus dieser Collection zu veröffentlichen.|Erstellen Sie den ersten Entrada, um Inhalte aus dieser Colección zu veröffentlichen.
L’injection de meta, les balises OG et JSON-LD sont actifs lorsque le SEO est publié.|L'injection de meta, les balises OG y JSON-LD sont actifs cuando le SEO est publie.
Hub-Anfrage échec (ungültige Serverantwort). Erneut versuchen ou Server-Logs prüfen.|Hub-Anfrage echec (ungultige Serverantwort). Erneut versuchen o Server-Logs prufen.
Optional: Der Entrée wird zu diesem Datum et dieser Uhrzeit automatisch depubliziert|Optional: Der Entrada wird zu diesem Datum y dieser Uhrzeit automatisch depubliziert
Consentement le plus élevé — demande au visiteur de confirmer avant de se connecter.|Consentement le plus eleve — demande au visitiour de confirmer avant de se connecter.
Automatisez vos flux de travail avec des déclencheurs, des conditions et des actions|Automatisez sus flux de travail con des declencheurs, des conditions y des actions
Pas encore publié. Vérifiez votre contenu, puis publiez à partir du haut de la page.|Pas encore publie. Verifiez su contenido, puis publiez a partir du haut de la página.
Impossible d’envoyer la révision : informations d’entrée ou de collection manquantes|Impossible d'envoyer la revision : informations d'entree o de collection manquantes
Veuillez vérifier que Birkly peut atteindre votre serveur Ollama avant de continuer.|Veuillez verifier que Birkly peut atteindre su serveur Ollama avant de continuer.
Diese Autorisation ist inaktiv. Gültige Licence aktivieren ou Checkout abschließen.|Diese Autorisation ist inaktiv. Gultige Licence aktivieren o Checkout abschließen.
Plugin-Anfrage échec (leere Serverantwort). Erneut versuchen ou Server-Logs prüfen.|Plugin-Anfrage echec (leere Serverantwort). Erneut versuchen o Server-Logs prufen.
Trouvez votre clé API et votre domaine d'envoi dans le panneau de contrôle Mailgun.|Trouvez su cle API y su domaine de envoi en le panneau de controle Mailgun.
Optional: Meine E-mail (%s) teilen, falls das Team Rückfragen zu diesem Bericht hat|Optional: Meine E-mail (%s) teilen, falls das Team Ruckfragen zu diesem Bericht hat
Jeder für CORS registrierte Hostname et wie er für Besucher et Admin geroutet wird.|Jeder fur CORS registrierte Hostname y wie er fur Besucher y Admin geroutet wird.
Aucune discussion récente pour le moment. Lancez une conversation pour le voir ici.|Ninguna discussion recente para le moment. Lancez une conversation para le voir ici.
Aucune Rechtsberatung — Sie bleiben für Datenschutz in Ihrer Region verantwortlich.|Ninguna Rechtsberatung — Sie bleiben fur Datenschutz in Ihrer Region verantwortlich.
Vérifiez cette collection pour d'éventuelles entrées en double ou très similaires.|Verifiez cette collection para de eventuelles entrees en double o tres similaires.
Veuillez tester la connexion avant de continuer ou corriger les erreurs affichées.|Veuillez tester la connexion avant de continuer o corriger les erreurs affichees.
URL du point de terminaison de l'API (requise pour les fournisseurs personnalisés)|URL du point de terminaison del API (requise para les fournisseurs personnalises)
Sélectionnez un utilisateur à notifier lorsque cette entrée est soumise à révision|Seleccione un utilisateur a notifier cuando cette entree est soumise a revision
Festlegen, wie Besucher Ihre Site erreichen — Aperçu-URL, Hosting et Auslieferung.|Festlegen, wie Besucher Ihre Sitio erreichen — Vista previa-URL, Hosting y Auslieferung.
Test: CMS-Root → Admin · öffentlicher Root → Site Web (aucun /project/ in der URL)|Test: CMS-Root → Admin · öffentlicher Root → Sitio Web (aucun /project/ in der URL)
Ersetzen — Pack-Collections + project entfernen, dann installieren (vorher Backup)|Ersetzen — Pack-Colecciones + project entfernen, dann installieren (vorher Backup)
Fügen Sie einen Entrée in posts hinzu et setzen Sie den Status auf Veröffentlicht.|Fugen Sie einen Entrada in posts hinzu y setzen Sie den Status auf Veröffentlicht.
Licence aktiviert. Installer Sie das Plugin auf dieser Site Web, um es zu nutzen.|Licence aktiviert. Installer Sie das Plugin auf dieser Sitio Web, um es zu nutzen.
CMS- et Site Web-URLs können Sie später unter Paramètres → Hosting konfigurieren.|CMS- y Sitio Web-URLs können Sie später unter Configuración → Hosting konfigurieren.
Cliquez sur Ajouter un agent pour créer une connexion partagée avec un rôle fixe.|Haga clic en Añadir un agent para crear une connexion partagee con un role fixe.
Vit toujours sur votre adresse Birkly — pas sur un hébergeur de site Web externe.|Vit toujours en su adresse Birkly — pas en un hebergeur de sitio Web externe.
Domaine registriert{role_msg}, Delivery-Rôle konnte pas gesetzt werden: {warning}|Domaine registriert{role_msg}, Delivery-Role konnte pas gesetzt werden: {warning}
Utilisateur „{email}“ wirklich supprimer? Das kann pas rückgängig gemacht werden.|Usuario „{email}“ wirklich eliminar? Das kann pas ruckgängig gemacht werden.
Chargement Sie Bilder et Fichiers hoch, um sie in Entréesn et Vorlagen zu nutzen.|Cargando Sie Bilder y Archisus hoch, um sie in Entradasn y Vorlagen zu nutzen.
Les pièces jointes de document ne sont pas prises en charge pour cette connexion.|Les pieces jointes de document ne sont pas prises en charge para cette connexion.
Impossible d'atteindre le serveur. Vérifiez votre connexion réseau et réessayez.|Impossible de atteindre le serveur. Verifiez su connexion reseau y reessayez.
Projetdateien, Vorlagen et Pfade verwalten, die Ihre öffentliche Site antreiben.|Projetdateien, Vorlagen y Pfade verwalten, die Ihre öffentliche Sitio antreiben.
Pas encore publié — terminer la configuration et publier lorsque vous êtes prêt.|Pas encore publie — terminer la configuration y publicar cuando usted etes pret.
Hub-Anfrage échec (leere Serverantwort). Erneut versuchen ou Server-Logs prüfen.|Hub-Anfrage echec (leere Serverantwort). Erneut versuchen o Server-Logs prufen.
Masquée du site en direct et de l’API publique. Visible uniquement dans l’admin.|Masquee du sitio en direct y de l'API publique. Visible uniquement en l'admin.
Änderungen auf diesem Tab werden beim Confirmer der jeweiligen Aktion enregistré|Änderungen auf diesem Tab werden beim Confirmar der jeweiligen Aktion enregistre
Tous les éléments de la liste de contrôle sont terminés — prêts à être publiés.|Todos les elements de la liste de controle sont termines — prets a etre publies.
Exemple : connectez une collection au fichier modèle qui affiche chaque entrée.|Exemple : connectez une collection au archivo modele qui affiche chaque entree.
Diese Autorisation ist abgelaufen. Erneuern Sie sie vor Installation ou Update.|Diese Autorisation ist abgelaufen. Erneuern Sie sie vor Installation o Update.
Conserve les utilisateurs admin, rôles, extensions et la plupart des paramètres|Conserve les utilisateurs admin, roles, extensions y la plupart des parametres
Connectez-vous avec votre compte Birkly — les autorisations suivent votre rôle.|Connectez-usted con su compte Birkly — les autorisations suivent su role.
Utilisée pour Open Graph et les cartes Twitter lorsqu’une page n’a pas d’image.|Utilisee para Open Graph y les cartes Twitter lorsqu'une página n'a pas d'image.
Ajoutez ce code à votre site Web avant la fermeture de la balise __BIRKLYPH0 __|Ajoutez ce code a su sitio Web avant la fermeture de la balise __BIRKLYPH0 __
Chaque entrée de collection liée à un modèle répétitif obtient sa propre fiche.|Chaque entree de collection liee a un modele repetitif obtient sa propre fiche.
Agent de service créé ! Copiez l'URL du connecteur dans votre application d'IA.|Agent de service cree ! Copiez el URL du connecteur en su application de IA.
Informations officielles sur l'IA pour les assistants de chat et de recherche :|Informations officielles en el IA para les assistants de chat y de recherche :
E-mail test envoyé. Vérifiez votre boîte de réception (et votre dossier spam).|E-mail test envoye. Verifiez su boite de reception (et su dossier spam).
Efface la progression d’intégration pour relancer l’assistant de configuration|Efface la progression d'integration para relancer l'assistant de configuration
Denylist-Ressourcen sind für alle Plugins tabu — unabhängig von Autorisations.|Denylist-Ressourcen sind fur alle Plugins tabu — unabhängig von Permisos.
Aucun champ défini pour le moment. Ajoutez des champs pour voir leurs limaces.|Ninguno champ defini para le moment. Ajoutez des champs para voir leurs limaces.
Consentement le plus élevé — demande au visiteur de confirmer avant de payer.|Consentement le plus eleve — demande au visitiour de confirmer avant de payer.
Paiement terminé. Collez votre clé d’activation pour terminer l’installation.|Pago termine. Collez su cle d'activation para terminer l'installation.
Par défautmäßig aus. Export- et Diagnose-Tools für erweiterte Installationen.|Par defautmäßig aus. Export- y Diagnose-Tools fur erweiterte Installationen.
Ajoutez cet extrait à chaque page (livre le Guide de l'agent automatiquement)|Ajoutez cet extrait a chaque página (livre le Guía del agent automatiquement)
URL directes à l'appui de cette affirmation (en plus des éléments de preuve).|URL directes a el appui de cette affirmation (en plus des elements de preuve).
Choisissez comment les entrées peuvent être créées pour cette sous-collection|Elija comment les entrees peuvent etre creees para cette sous-collection
Créer et modifier des projets/ HTML, CSS et JS avec des outils de contenu CMS|Crear y editar des projets/ HTML, CSS y JS con des outils de contenido CMS
Installer Sie ein Site-Pack unter Entdecken ou charger Sie ein Pack-ZIP hoch.|Installer Sie ein Sitio-Pack unter Entdecken o charger Sie ein Pack-ZIP hoch.
Ajustez la taille et l'espacement du texte dans l'interface d'administration.|Ajustez la taille y el espacement du texte en el interface de administration.
Aucun champ défini pour le moment. Ajoutez des champs pour voir leurs slugs.|Ninguno champ defini para le moment. Ajoutez des champs para voir leurs slugs.
Modifiez l’apparence de cette entrée dans Google et sur les réseaux sociaux.|Modifiez l'apparence de cette entree en Google y en les reseaux sociaux.
Vous devez d'abord configurer une connexion IA pour utiliser l'assistant IA.|Usted devez de abord configurer une connexion IA para utiliser el assistant IA.
Nur Lesen: Inhalte abrufen. Lesen & Schreiben: Inhalte abrufen et einsenden.|Nur Lesen: Inhalte abrufen. Lesen & Schreiben: Inhalte abrufen y einsenden.
Votre session a expiré. Merci de rafraichir la page et d’essayer à nouveau.|Su session a expire. Merci de rafraichir la página y d'essayer a nuevo.
Envoyez via AWS Simple Email Service avec vos clés d'accès et votre région.|Envoyez via AWS Simple Email Service con sus cles de acces y su region.
Définissez les champs que les sous-entrées de cette sous-collection auront.|Definissez les champs que les sous-entrees de cette sous-collection auront.
Ouvrir Sie Hub → Entdecken et wählen Sie ein passendes offizielles Plugin.|Abrir Sie Hub → Entdecken y wählen Sie ein passendes offizielles Plugin.
Noch aucune Domainees. „Domaine hinzufügen“ klicken, um eine zu verbinden.|Noch aucune Domainees. „Domaine hinzufugen“ klicken, um eine zu verbinden.
Cet agent de service utilise un rôle fixe défini par votre administrateur.|Cet agent de service utilise un role fixe defini par su administrateur.
Démarrer une nouvelle discussion ? Cela effacera la conversation en cours.|Demarrer une nueva discussion ? Cela effacera la conversation en cours.
Version en direct {v} — instantané publié des ébauches de correspondances.|Versión en direct {v} — instantane publie des ebauches de correspondances.
Vit sur votre site Web en tant que fichier public que les IA peuvent lire.|Vit en su sitio Web en tant que archivo public que les IA peuvent lire.
Optional: Screenshot in die Beschreibung einfügen, Fichiers anhängen ou an|Optional: Screenshot in die Beschreibung einfugen, Archisus anhängen o an
Saisissez un code de votre application d’authentification ou laissez vide|Introduzca un code de su application d'authentification o laissez vide
Préversion bêta. Des problèmes peuvent subsister avant la version stable.|Preversion beta. Des problemes peuvent subsister avant la version stable.
Domaine „{name}“ wirklich supprimer? Der gesamte Zugriff wird widerrufen.|Domaine „{name}“ wirklich eliminar? Der gesamte Zugriff wird widerrufen.
Impossible d'atteindre le serveur. Vérifiez votre connexion et réessayez.|Impossible de atteindre le serveur. Verifiez su connexion y reessayez.
Impossible de charger les connexions e-mail. Veuillez actualiser la page.|No se puede charger les connexions e-mail. Veuillez actualiser la página.
Votre fiche d'information publique sur l'IA et où les IA peuvent la lire.|Su fiche de information publique en el IA y ou les IA peuvent la lire.
Facultatif : ajoutez une protection CAPTCHA (nécessite une configuration)|Opcional : ajoutez une protection CAPTCHA (necessitio une configuration)
Test envoyé ! Vérifiez votre boîte de réception et votre dossier de spam.|Test envoye ! Verifiez su boite de reception y su dossier de spam.
Dieses Plugin wirklich supprimer? Das kann pas rückgängig gemacht werden.|Dieses Plugin wirklich eliminar? Das kann pas ruckgängig gemacht werden.
En attente d’approbation du relecteur. Non visible sur le site en direct.|Pendiente d'approbation du relecteur. No visible en le sitio en direct.
Dossier „{name}“ supprimer et Fichiers ins Stammverzeichnis verschieben?|Carpeta „{name}“ eliminar y Archisus ins Stammverzeichnis verschieben?
Saisissez la clé de licence reçue dans l’e-mail de confirmation d’achat.|Introduzca la cle de licence recue en l'e-mail de confirmation d'achat.
Aucune collection CMS trouvée — créez d'abord des collections de contenu|Ninguna collection CMS trouvee — creez de abord des collections de contenido
Toutes les entrées du CMS liées correspondent à vos champs de brouillon.|Todas les entrees du CMS liees correspondent a sus champs de brouillon.
Entrez votre clé API, puis testez la connexion pour charger les modèles.|Entrez su cle API, puis testez la connexion para charger les modeles.
Appliquer cette mise à jour de champ à %count% %entity% sélectionné(s) ?|Appliquer cette mise a jour de champ a %count% %entity% selectionne(s) ?
Choisissez comment les entrées peuvent être créées pour cette collection|Elija comment les entrees peuvent etre creees para cette collection
Titre pour la recherche (laisser vide pour la valeur par défaut du site)|Título para la recherche (laisser vide para la valeur par defaut du sitio)
Les fichiers modèles autonomes apparaissent ici après une actualisation.|Les archisus modeles autonomes apparaissent ici apres une actualisation.
Kurzüberblick zu Inhalt, Médias, Discovery et wichtigen Paramètres-Tabs.|Kurzuberblick zu Inhalt, Medios, Discovery y wichtigen Configuración-Tabs.
URL où votre Guide de l'agent publié est servi après votre publication.|URL ou su Guía del agent publie est servi apres su publicación.
L'authentification à deux facteurs n'est pas activée pour votre compte.|L'authentification a deux facteurs no est pas activee para su compte.
Lettres minuscules, chiffres, points, tirets et traits de soulignement.|Lettres minuscules, chiffres, points, tirets y traits de soulignement.
Une étiquette que vous reconnaîtrez dans la liste — vous seul la voyez.|Une etiquette que usted reconnaitrez en la liste — usted seul la voyez.
Avancé : utilisez un modèle de chemin au lieu de paramètres de requête.|Avance : utilisez un modele de chemin au lieu de parametres de requete.
Titre de la page d’accueil et de secours avant application du suffixe.|Título de la página d'accueil y de secours avant application du suffixe.
Diese Rôle wirklich supprimer? Das kann pas rückgängig gemacht werden.|Diese Role wirklich eliminar? Das kann pas ruckgängig gemacht werden.
Version en direct {v} — le brouillon a des modifications non publiées.|Versión en direct {v} — le brouillon a des modifications non publiees.
Ce fournisseur n'est pas pris en charge pour les connexions sortantes.|Ce fournisseur no est pas pris en charge para les connexions sortantes.
Inhaltskollektionen im Vorlagenexport (nie Utilisateur ou Geheimnisse)|Inhaltskollektionen im Vorlagenexport (nie Usuario o Geheimnisse)
Installer Sie das Plugin et aktivieren Sie es auf dem Tab Installiert.|Installer Sie das Plugin y aktivieren Sie es auf dem Tab Installiert.
— aide au développement local pour Claude Desktop (avancé uniquement).|— aide au developpement local para Claude Desktop (avance uniquement).
Aucune — nur CMS (aucune öffentliche Site Web auf dieser Installation)|Ninguna — nur CMS (aucune öffentliche Sitio Web auf dieser Installation)
Actualisez la page. Si cela persiste, contactez votre administrateur.|Actualisez la página. Si cela persiste, contactez su administrateur.
S'exécute dans le navigateur du visiteur, sur la page qu'il consulte.|S'execute en le navigateur du visitiour, en la página que il consulte.
Ajouter un document — ou coller / déposer dans la boîte de discussion|Añadir un document — o coller / deposer en la boite de discussion
Paiement terminé. Vérifiez et installez avec la clé de licence reçue.|Pago termine. Verifiez y installez con la cle de licence recue.
Connexion enregistrée — publications ajoutées aux listes de recherche|Conexión enregistree — publicacións ajoutees aux listes de recherche
Logo wirklich entfernen? Das Par défaut-Birkly-Symbol wird verwendet.|Logo wirklich entfernen? Das Par defaut-Birkly-Symbol wird verwendet.
Supprimer cette connexion d'assistant ? Cela ne peut pas être annulé.|Eliminar cette connexion de assistant ? Cela ne peut pas etre annule.
Test enregistré avec succès (mode Log only — check logs/mail.jsonl).|Test enregistre con succes (mode Log only — check logs/mail.jsonl).
Cette extension arrive bientôt et ne peut pas encore être installée.|Cette extension arrive bientot y ne peut pas encore etre installee.
Manueller Zip-Upload ist noch pas verfügbar. Nutzen Sie den Hub-Tab.|Manueller Zip-Upload ist noch pas verfugbar. Nutzen Sie den Hub-Tab.
Qui doit recevoir un e-mail lorsque quelqu'un soumet ce formulaire ?|Qui doit recevoir un e-mail cuando quelqu'un soumet ce formulaire ?
Aucune collection trouvée. Créez d'abord des collections de contenu.|Ninguna collection trouvee. Creez de abord des collections de contenido.
Hub-Anfrage-Zeitüberschreitung. Netzwerk prüfen et erneut versuchen.|Hub-Anfrage-Zeituberschreitung. Netzwerk prufen y erneut versuchen.
ID de collection introuvable. Impossible de supprimer la collection.|ID de collection introuvable. No se puede eliminar la collection.
Löscht alle Fichiers in project/ unwiderruflich. Vorher ZIP sichern.|Löscht alle Archisus in project/ unwiderruflich. Vorher ZIP sichern.
Envoyer via Mailgun — entrez votre domaine et votre clé API privée.|Envoyer via Mailgun — entrez su domaine y su cle API privee.
Aucun outil ne serait encore exposé — activez les outils ci-dessus.|Ninguno outil ne serait encore expose — activez les outils ci-dessus.
Publier le guide de l'agent sur les points de terminaison publics ?|Publicar le guide del agent en les points de terminaison publics ?
Nur Ops-Starter-/Site-Pack-Format. Ungültige ZIPs werden abgelehnt.|Nur Ops-Starter-/Sitio-Pack-Format. Ungultige ZIPs werden abgelehnt.
Aucun paramètre supplémentaire n'est nécessaire pour cette méthode.|Ninguno parametre supplementaire no est necessaire para cette methode.
Impossible de copier — veuillez sélectionner le texte manuellement.|No se puede copier — veuillez selectionner le texte manuellement.
Erreur lors de la suppression de la collection. Veuillez réessayer.|Error cuando de la suppression de la collection. Veuillez reessayer.
Rédigez un court extrait ou une méta-description pour cette entrée.|Redigez un court extrait o une meta-description para cette entree.
Ignorez l'assistant et utilisez Essentials ou les onglets complets.|Ignorez el assistant y utilisez Essentials o les onglets complets.
Ajouter une image — ou coller / déposer dans la boîte de discussion|Añadir une image — o coller / deposer en la boite de discussion
Pour un aperçu détaillé de la création de modèles, veuillez visiter|Pour un apercu detaille de la creation de modeles, veuillez visitior
Les boutons d’achat appliquent la remise sélectionnée au paiement.|Les boutons d'achat appliquent la remise selectionnee au paiement.
Sera publiée automatiquement à la date définie. Masquée jusque-là.|Sera publiee automatiquement a la date definie. Masquee jusque-la.
URL locale Ollama (par défaut : http ://localhost :11434/api/chat)|URL locale Ollama (par defaut : http ://localhost :11434/api/chat)
Informe Bing et ses partenaires lorsque vous publiez ou republiez.|Informe Bing y ses partenaires cuando usted publiez o republiez.
ID de collection introuvable. Impossible de dupliquer la collecte.|ID de collection introuvable. No se puede dupliquer la collecte.
Dissocier votre compte cloud Birkly de cette installation de CMS ?|Dissocier su compte cloud Birkly de cette installation de CMS ?
Erreur lors de la régénération des codes de sauvegarde : {message}|Error cuando de la regeneration des codes de sauvegarde : {message}
Erreur lors du chargement de l’historique des versions : {message}|Error cuando du chargement de l'historique des versions : {message}
Activer la prise en charge multilingue pour cette sous-collection|Activer la prise en charge multilingue para cette sous-collection
Öffentlicher Alias — Besucher sehen die Site auf diesem Hostnamen|Öffentlicher Alias — Besucher sehen die Sitio auf diesem Hostnamen
Cliquez en dehors du champ de langue, puis enregistrez à nouveau.|Haga clic en dehors du champ de langue, puis enregistrez a nuevo.
Être notifié des échecs de connexion et des problèmes de sécurité|Être notifie des echecs de connexion y des problemes de securite
Licence aktiviert — Plugin auf dieser Site Web noch pas installé.|Licence aktiviert — Plugin auf dieser Sitio Web noch pas installe.
Erreur de soumission du formulaire. Veuillez réessayer plus tard.|Error de soumission du formulaire. Veuillez reessayer plus tard.
La publication est désactivée — activez-la dans l'onglet Publier.|La publicación est desactivee — activez-la en el onglet Publicar.
Définissez les champs que les entrées de cette collection auront.|Definissez les champs que les entrees de cette collection auront.
Aucune öffentliche Site Web auf dieser Installation konfiguriert.|Ninguna öffentliche Sitio Web auf dieser Installation konfiguriert.
Apprenez les fonctionnalités de Birkly avec une assistance guidée|Apprenez les fonctionnalites de Birkly con une assistance guidee
Déclarations factuelles Les systèmes d'IA peuvent faire confiance|Declarations factuelles Les systemes de IA peuvent faire confiance
Erreur lors de la duplication de la collecte. Veuillez réessayer.|Error cuando de la duplication de la collecte. Veuillez reessayer.
Choisissez comment les sorties SEO atteignent votre site public.|Elija comment les sorties SEO atteignent su sitio public.
Erreur lors de l'enregistrement des groupes. Veuillez réessayer.|Error cuando del enregistrement des groupes. Veuillez reessayer.
{n} proposition(s) pour un profil complet — examiner et accepter|{n} proposition(s) para un profil complet — examiner y accepter
L'URL de l'API est requise pour les fournisseurs personnalisés.|L'URL del API est requise para les fournisseurs personnalises.
Saisissez l'ID de modèle requis par votre point de terminaison.|Introduzca el ID de modele requis par su point de terminaison.
Vous pouvez connecter un assistant IA à tout moment à partir de|Usted pouvez connecter un assistant IA a tout moment a partir de
Recherche, faits pour l’IA et outils Web pour votre site public|Recherche, faits para l'IA y outils Web para su sitio public
Commerce, Events et weitere offizielle Erweiterungen hinzufügen|Commerce, Events y weitere offizielle Erweiterungen hinzufugen
Étape par étape — idéal pour la première publication (~15 min).|Paso par paso — ideal para la premiere publicación (~15 min).
Seuls les fichiers image peuvent être joints en tant qu'images.|Seuls les archisus image peuvent etre joints en tant que images.
Changer de connexion AI ? Cela démarre une nouvelle discussion.|Changer de connexion AI ? Cela demarre une nueva discussion.
CMS-Alias — Admin, API et birkly-client.js auf diesem Hostnamen|CMS-Alias — Admin, API y birkly-client.js auf diesem Hostnamen
Diese Licence hat das Actifierungs- ou Sitzplatzlimit erreicht.|Diese Licence hat das Activoierungs- o Sitzplatzlimit erreicht.
Vérifiez ce que cette extension peut accéder avant d’installer.|Verifiez ce que cette extension peut acceder avant d'installer.
Votre site Birkly est prêt. Voici quelques façons de commencer.|Su sitio Birkly est pret. Voici quelques facons de commencer.
Birkly ist installé. Wenige Schritte zum Abschluss des Setups.|Birkly ist installe. Wenige Schritte zum Abschluss des Setups.
Tage zum Actifieren von 2FA nach Policy-Start (Par défaut 30).|Tage zum Activoieren von 2FA nach Policy-Start (Par defaut 30).
Copié. Claude a besoin de HTTPS pour les connecteurs distants.|Copie. Claude a besoin de HTTPS para les connecteurs distants.
Setup überspringen? Sie können später im Dashboard fortfahren.|Setup uberspringen? Sie können später im Dashboard fortfahren.
Saisissez la clé de licence pour révoquer ce siège sur le Hub.|Introduzca la cle de licence para revoquer ce siege en le Hub.
Nombre maximum de soumissions autorisées par heure par clé API|Nombrebre maximum de soumissions autorisees par heure par cle API
Vor Migration Host, Base de donnéesname et Utilisateur angeben|Vor Migration Host, Base de donneesname y Usuario angeben
Être notifié lorsque votre rôle ou vos autorisations changent|Être notifie cuando su role o sus autorisations changent
Envoyez un test rapide pour vous assurer que tout fonctionne.|Envoyez un test rapide para usted assurer que tout fonctionne.
Vérifier avec un inspecteur d'outil de navigateur (technique)|Verifier con un inspecteur de outil de navigateur (technique)
Sélectionnez une réclamation pour afficher les preuves liées.|Seleccione une reclamation para afficher les preuves liees.
PDF, DOCX, XLSX, TXT, MD, ZIP — jusqu'à vos limites de médias|PDF, DOCX, XLSX, TXT, MD, ZIP — hasta sus limites de medias
Produit enregistré — n'oubliez pas d'enregistrer le brouillon|Producto enregistre — no oubliez pas de guardar le brouillon
Sélectionnez d'abord une connexion IA dans la barre latérale|Seleccione de abord une connexion IA en la barre laterale
Exécutez la configuration guidée pour publier en ~15 minutes|Executez la configuration guidee para publicar en ~15 minutes
Teilen Sie Ihre Erfahrung mit diesem Plugin auf Ihrer Site.|Teilen Sie Ihre Erfahrung mit diesem Plugin auf Ihrer Sitio.
Chargez les modèles après avoir sélectionné un fournisseur.|Chargez les modeles apres avoir selectionne un fournisseur.
L’entrée sera publiée automatiquement à cette date et heure|L'entree sera publiee automatiquement a cette date y heure
Ajoutez cet extrait avant la balise </body> sur votre site.|Ajoutez cet extrait avant la balise </body> en su sitio.
CMS, sites Web, documents — où les agents lisent en premier|CMS, sitios Web, documents — ou les agents lisent en premier
Ouvrez le site modèle de démarrage installé avec votre CMS.|Ouvrez le sitio modele de demarrage installe con su CMS.
__ BIRKLYPH0__ proposition(s) prête (s) à être examinée (s)|__ BIRKLYPH0__ proposition(s) prete (s) a etre examinee (s)
Connectez-vous à votre compte administrateur__ BIRKLYPH0 __|Connectez-usted a su compte administrateur__ BIRKLYPH0 __
 endgültig supprimer. Nicht rückgängig. Vorher ZIP sichern.| endgultig eliminar. Nicht ruckgängig. Vorher ZIP sichern.
Pour une vue d’ensemble détaillée du templating, consultez |Pour une vue d'ensemble detaillee du templating, consultez 
Être notifié lorsqu’un commentaire est ajouté à vos entrées|Être notifie lorsqu'un commentaire est ajoute a sus entrees
Être notifié lorsque des mises à jour CMS sont disponibles|Être notifie cuando des mises a jour CMS sont disponibles
Erreur lors de la suppression de la configuration de suivi|Error cuando de la suppression de la configuration de suivi
Erreur beim Ändern der Sprache. Veuillez erneut versuchen.|Error beim Ändern der Sprache. Veuillez erneut versuchen.
Échec du réordonnancement des entrées. Veuillez réessayer.|Échec du reordonnancement des entrees. Veuillez reessayer.
Êtes-vous sûr de vouloir supprimer cette sous-collection ?|¿Está seguro de vouloir eliminar cette sous-collection ?
Feedback aus der geschlossenen Alpha prüfen et bearbeiten.|Feedback aus der geschlossenen Alpha prufen y bearbeiten.
Définir ce qui se passe lorsque l'automatisation s'exécute|Definir ce qui se passe cuando el automatisation se execute
Publication automatique des soumissions (si API publique)|Publicación automatique des soumissions (si API publique)
Êtes-vous sûr(e) de vouloir supprimer cette entrée&#160;?|¿Está seguro(e) de vouloir eliminar cette entree&#160;?
Erreur réseau — impossible d'atteindre le serveur Birkly.|Error reseau — impossible de atteindre le serveur Birkly.
Zusammenführen — Pack-Fichiers überlagern (vorher Backup)|Zusammenfuhren — Pack-Archisus uberlagern (vorher Backup)
Bis zu 5 Fichiers, je 5 MB — JPEG, PNG, GIF, WebP ou MP4.|Bis zu 5 Archisus, je 5 MB — JPEG, PNG, GIF, WebP o MP4.
Ce nom est affiché dans tout le panneau d’administration.|Ce nom est affiche en tout le panneau d'administration.
Impossible de charger les informations d'identification.|No se puede charger les informations de identification.
Déposez vos fichiers ici ou cliquez pour les téléverser.|Deposez sus archisus ici o cliquez para les subir.
Intitulé/Désignation/Etiquette utilisé(é) sur le terrain|Intitule/Designation/Etiquette utilise(e) en le terrain
Publier les mises à jour du Guide de l'agent en direct ?|Publicar les mises a jour du Guía del agent en direct ?
Modifier-Dialog pas gefunden. Veuillez Page neu charger.|Editar-Dialog pas gefunden. Veuillez Página neu charger.
Dieses Plugin auf die neueste Hub-Version aktualisieren?|Dieses Plugin auf die neueste Hub-Versión aktualisieren?
Wenn CMS et öffentliche Site Web dieselbe Domaine nutzen|Wenn CMS y öffentliche Sitio Web dieselbe Domaine nutzen
Erreur lors de la restauration de la version : {message}|Error cuando de la restauration de la version : {message}
Saisissez votre clé API pour charger les modèles de %s.|Introduzca su cle API para charger les modeles de %s.
Sélectionnez une connexion IA pour la génération de CMS|Seleccione une connexion IA para la generation de CMS
Erreur lors de l’enregistrement de l’entrée : {message}|Error cuando de l'enregistrement de l'entree : {message}
Le serveur a renvoyé une réponse non valide. Réessayez.|Le serveur a renvoye une reponse non valide. Reessayez.
Fichiers werden in den aktuellen Dossier hochgecharger.|Archisus werden in den aktuellen Carpeta hochgecharger.
Vous devez accepter les autorisations avant d’installer|Usted devez accepter les autorisations avant d'installer
Sprache für das CMS-Admin. Später änderbar unter Konto.|Sprache fur das CMS-Admin. Später änderbar unter Konto.
Sélectionnez une réclamation pour afficher les détails.|Seleccione une reclamation para afficher les details.
Ändert nur die Admin-Oberflächensprache für dein Konto.|Ändert nur die Admin-Oberflächensprache fur dein Konto.
Ajoutez les balises de script ci-dessous à chaque page.|Ajoutez les balises de script ci-dessous a chaque página.
Visible sur votre site et incluse dans l’API publique.|Visible en su sitio y incluse en l'API publique.
Valeurs par défaut proposées — vérifiez et enregistrez|Valeurs par defaut proposees — verifiez y enregistrez
Rédiger et modifier les collections et les entrées CMS|Rediger y editar les collections y les entrees CMS
Supprimer « {label} » ? Cette action est irréversible.|Eliminar « {label} » ? Esta acción no se puede deshacer.
Activer la publication publique pour les systèmes d'IA|Activer la publicación publique para les systemes de IA
Ein Erreur ist aufgetreten. Veuillez erneut versuchen.|Ein Error ist aufgetreten. Veuillez erneut versuchen.
Sélectionner la collecte et la saisie pour le lien CMS|Selectionner la collecte y la saisie para le lien CMS
Spracheinstellung enregistré. Page wird neu gecharger…|Spracheinstellung enregistre. Página wird neu gecharger…
Posez des questions sur votre contenu en langage clair|Posez des questions en su contenido en langage clair
Obtenir de l'aide pour rédiger et modifier des entrées|Obtenir del aide para rediger y editar des entrees
Connectez d'abord les entrées du CMS (onglet Contexte)|Connectez de abord les entrees du CMS (onglet Contexte)
Actions préparées pour votre examen (voir ci-dessous).|Acciones preparees para su examen (voir ci-dessous).
Complétez ces étapes pour mettre votre site en ligne :|Completez ces pasos para mettre su sitio en ligne :
Publié, mais vous avez des modifications non publiées.|Publicado, mais usted avez des modifications non publiees.
Licenceschlüssel an diese Birkly-Installation binden.|Licenceschlussel an diese Birkly-Installation binden.
Être notifié lorsqu’une entrée est soumise à révision|Être notifie lorsqu'une entree est soumise a revision
Rôlen-Dialog pas gefunden. Veuillez Page neu charger.|Rolen-Dialog pas gefunden. Veuillez Página neu charger.
Le nom officiel et une brève description sont requis.|Le nom officiel y une breve description sont requis.
{count} entrées publiées dans les listes de recherche|{count} entrees publiees en les listes de recherche
Erreur beim Chargement der Delivery-Matrix: {message}|Error beim Cargando der Delivery-Matrix: {message}
Nur frisch — ablehnen, wenn die Site Web pas leer ist|Nur frisch — ablehnen, wenn die Sitio Web pas leer ist
Suggestions de liens prêtes — révision dans AI Assist|Suggestions de liens pretes — revision en AI Assist
Les destinataires voient cela comme l'adresse « De ».|Les destinataires voient cela comme el adresse « De ».
Erreur lors du chargement des configurations de suivi|Error cuando du chargement des configurations de suivi
Update réussi installé. Die Page wird neu gecharger.|Update reussi installe. Die Página wird neu gecharger.
Action de révision envoyée. Rechargement de la page…|Acción de revision envoyee. Rechargement de la página…
Les applications d'IA utilisent cette URL Birkly MCP|Les applications de IA utilisent cette URL Birkly MCP
Activer les vues récapitulatives et API des produits|Activer les vues recapitulatives y API des produits
Activer le support multilingue pour cette collection|Activer le support multilingue para cette collection
Sélecteur de médias indisponible — rechargez la page|Selecteur de medias indisponible — rechargez la página
Terminez ces étapes pour mettre votre site en ligne.|Terminez ces pasos para mettre su sitio en ligne.
Prüfen Sie erneut, sobald neue Releases publié sind.|Prufen Sie erneut, sobald neue Releases publie sind.
Comment les applications d'IA se connectent à Birkly|Comment les applications de IA se connectent a Birkly
Aucune autorisation élevée déclarée dans plugin.json|Ninguna autorisation elevee declaree en plugin.json
Impossible de mettre à jour le connecteur personnel|No se puede mettre a jour le connecteur personnel
Les chemins source et destination sont obligatoires|Les chemins source y destination sont obligatoires
Impossible de copier le lien dans le presse-papiers|No se puede copier le lien en le presse-papiers
Wo CMS et öffentliche Site Web ausgeliefert werden.|Wo CMS y öffentliche Sitio Web ausgeliefert werden.
Erreur : Domaine et Kunden-E-mail sind erforderlich|Error : Domaine y Kunden-E-mail sind erforderlich
Publié à {time} — aucune modification non publiée.|Publicado a {time} — aucune modification non publiee.
Veuillez consulter le (s) fichier(s) ci-joint (s).|Veuillez consulter le (s) archivo(s) ci-joint (s).
Dossier „{name}“ et enthaltene Fichiers supprimer?|Carpeta „{name}“ y enthaltene Archisus eliminar?
__ BIRKLYPH0__ action(s) exécutée (s) avec succès.|__ BIRKLYPH0__ action(s) executee (s) con succes.
Échec de la suppression de la collecte : {message}|Error al la suppression de la collecte : {message}
Échec de la duplication de la collecte : {message}|Error al la duplication de la collecte : {message}
Veuillez sélectionner les deux versions à comparer|Veuillez selectionner les deux versions a comparer
Erreur lors de l'enregistrement de la collection :|Error cuando del enregistrement de la collection :
Erreur lors de la désactivation de 2FA : {message}|Error cuando de la desactivation de 2FA : {message}
Définir quand cette automatisation doit s'exécuter|Definir quand cette automatisation doit se executer
Integrierte Assistenten-Schlüssel verwalten unter |Integrierte Assistenten-Schlussel verwalten unter 
Pièces jointes __BIRKLYPH0 __ maximum par message.|Pieces jointes __BIRKLYPH0 __ maximum par message.
Erreur lors du chargement des détails de connexion|Error cuando du chargement des details de connexion
Projetinformationen konnten pas enregistré werden.|Projetinformationen konnten pas enregistre werden.
Résultats des actions et erreurs de cette session|Resultats des actions y erreurs de cette session
Aucune collection ne correspond à votre recherche|Ninguna collection ne correspond a su recherche
Erreur lors de l’envoi de la révision : {message}|Error cuando de l'envoi de la revision : {message}
Renseignez-vous sur votre guide de l'agent publié|Renseignez-usted en su guide del agent publie
Échec du réordonnancement des entrées : {message}|Échec du reordonnancement des entrees : {message}
Veuillez tester la connexion avant d'enregistrer.|Veuillez tester la connexion avant de guardar.
Générer automatiquement le slug à partir du titre|Generer automatiquement le slug a partir du titre
Erreur : Ungültige Serverantwort. Konsole prüfen.|Error : Ungultige Serverantwort. Konsole prufen.
Erreur lors de la création de la configuration de|Error cuando de la creation de la configuration de
Hosting-Paramètres konnten pas enregistré werden|Hosting-Configuración konnten pas enregistre werden
Documents non pris en charge pour ce fournisseur|Documents non pris en charge para ce fournisseur
Veuillez contacter votre Administrateur Système.|Veuillez contacter su Administrador Systeme.
Impossible d'enregistrer les groupes : {message}|Impossible de guardar les groupes : {message}
Erreur beim Abmelden. Veuillez erneut versuchen.|Error beim Abmelden. Veuillez erneut versuchen.
 — Tools folgen Rôlenberechtigungen (Par défaut)| — Tools folgen Rolenberechtigungen (Par defaut)
par exemple, aide à rédiger des articles de blog|par exemple, aide a rediger des articles de blog
Moteurs de recherche ping sur publish (IndexNow)|Moteurs de recherche ping en publish (IndexNow)
Noch aucune ausführliche Beschreibung vorhanden.|Noch aucune ausfuhrliche Beschreibung vorhanden.
Veuillez sélectionner deux versions différentes|Veuillez selectionner deux versions differentes
Lern-Site in project/, aucune Demo-Collectionen|Lern-Sitio in project/, aucune Demo-Colecciónen
Injection automatique (site hébergé par Birkly)|Injection automatique (sitio heberge par Birkly)
Sélectionner les utilisateurs pour la connexion|Selectionner les utilisateurs para la connexion
Votre schéma de sous-collection est enregistré.|Su schema de sous-collection est enregistre.
Admin-Panel, API et birkly-client.js Basis-URL.|Admin-Panel, API y birkly-client.js Basis-URL.
Mettre à jour les informations d'identification|Mettre a jour les informations de identification
Échec du chargement des détails de la connexion|Échec du chargement des details de la connexion
Être notifié lorsqu’une invitation est acceptée|Être notifie lorsqu'une invitation est acceptee
Passez en direct sur l'URL de votre site public|Passez en direct en el URL de su sitio public
Ouvrir AI Assistant (__ BIRKLYPH0__ en attente)|Abrir AI Assistant (__ BIRKLYPH0__ en attente)
Modifier les informations d'identification HTTP|Editar les informations de identification HTTP
Téléchargez une photo de profil (JPG/PNG/WebP).|Telechargez une photo de profil (JPG/PNG/WebP).
Manuel (utilisez l'étiquette de prix ci-dessus)|Manuel (utilisez el etiquette de prix ci-dessus)
Votre adresse e-mail ne peut pas être modifiée.|Su adresse e-mail ne peut pas etre modifiee.
3. Téléchargez le fichier (facultatif, manuel)|3. Telechargez le archivo (facultatif, manuel)
Aucune ausstehenden Änderungen zum Enregistrer|Ninguna ausstehenden Änderungen zum Guardar
Domainenamen ohne http:// ou https:// eingeben|Domainenamen ohne http:// o https:// eingeben
Erreur lors de l'activation de 2FA : {message}|Error cuando del activation de 2FA : {message}
Continuer manuellement — L'IA est facultative.|Continuar manuellement — L'IA est facultative.
Erreur lors de l’opération groupée : {message}|Error cuando de l'operation groupee : {message}
Collectionen enthalten die Inhalte Ihrer Site.|Colecciónen enthalten die Inhalte Ihrer Sitio.
Êtes-vous sûr de vouloir supprimer ce champ ?|¿Está seguro de vouloir eliminar ce champ ?
Erreur lors de l'envoi du message : {message}|Error cuando del envoi du message : {message}
Wie Projetseiten gerendert et gecacht werden.|Wie Projetseiten gerendert y gecacht werden.
Étape 3 : Enregistrer les codes de sauvegarde|Paso 3 : Guardar les codes de sauvegarde
Activer l'authentification à 2 facteurs (2FA)|Activer el authentification a 2 facteurs (2FA)
Cette extension n’est pas encore installable.|Cette extension n'est pas encore installable.
Erreur beim Enregistrer der Spracheinstellung|Error beim Guardar der Spracheinstellung
Erreur lors du chargement du diff : {message}|Error cuando du chargement du diff : {message}
Voulez-vous vraiment supprimer votre avatar ?|Voulez-usted vraiment eliminar su avatar ?
Propositions appliquées — vérifiez et publiez|Propositions appliquees — verifiez y publiez
Chargement des informations d'identification…|Cargando des informations de identification…
guide d'installation / guide de configuration|guide de installation / guide de configuration
Aucun modèle ne correspond à votre recherche.|Ninguno modele ne correspond a su recherche.
Wählen Sie zuerst ein Base de données-Backend|Wählen Sie zuerst ein Base de donnees-Backend
Besucherstatistik für diese Domaine erfassen.|Besucherstatistik fur diese Domaine erfassen.
Synchroniser à partir du champ de saisie CMS|Synchroniser a partir du champ de saisie CMS
L’ancien chemin qui doit rediriger ailleurs.|L'ancien chemin qui doit rediriger ailleurs.
Richtlinie für Zwei-Faktor-Authentifizierung|Richtlinie fur Zwei-Faktor-Authentifizierung
Claude nécessite une URL de connecteur HTTPS|Claude necessitio une URL de connecteur HTTPS
Un problème est survenu. Veuillez réessayer.|Un probleme est survenu. Veuillez reessayer.
Échec de la suppression des données de suivi|Error al la suppression des donnees de suivi
Aucun agent de service ne vous a été assigné|Ninguno agent de service ne usted a ete assigne
Exiger une approbation pour les actions d'IA|Exiger une approbation para les actions de IA
Suggérer de la valeur en utilisant AI Assist|Suggerer de la valeur en utilisant AI Assist
Médiasdetails konnten pas enregistré werden:|Mediosdetails konnten pas enregistre werden:
Ce n'est donc pas la plateforme idéale pour |Ce no est donc pas la plateforme ideale para 
Créer un sous-titre de compte administrateur|Crear un sous-titre de compte administrateur
Automatiquement actif sur votre site Birkly.|Automatiquement actif en su sitio Birkly.
Sélectionnez d'abord un fournisseur de scorm|Seleccione de abord un fournisseur de scorm
Collection introuvable. Actualisez la page.|Colección introuvable. Actualisez la página.
Aide sur la conservation du contenu de démo|Ayuda en la conservation du contenido de demo
Starter-Vorlage konnte pas installé werden.|Starter-Vorlage konnte pas installe werden.
Veuillez saisir un code valide à 6 chiffres|Veuillez saisir un code valide a 6 chiffres
Aucun Update zum Heruntercharger verfügbar.|Ninguno Update zum Heruntercharger verfugbar.
Ajoutez ces balises de script à chaque page|Ajoutez ces balises de script a chaque página
Enregistrer les préférences de notification|Guardar les preferences de notification
Inclure JSON-LD dans l'exportation publique|Inclure JSON-LD en el exportation publique
Quelles allégations sont prises en charge ?|Quelles allegations sont prises en charge ?
Ajouter une sous-collection sous ({{slug}})|Añadir une sous-collection sous ({{slug}})
Je comprends et j’accepte ces autorisations|Je comprends y j'accepte ces autorisations
Saisissez le code ou le nom de la langue...|Introduzca le code o le nom de la langue...
Aucune révision enregistrée pour le moment.|Ninguna revision enregistree para le moment.
Erreur beim Chargement der Domaine-Details:|Error beim Cargando der Domaine-Details:
Comment les applications d'IA se connectent|Comment les applications de IA se connectent
Parcourir ou coller un emoji pour ce groupe|Parcourir o coller un emoji para ce groupe
Connexions de l'application IA (Birkly MCP)|Conexións del application IA (Birkly MCP)
Enregistrer Sie den Webhook vor dem Testen.|Guardar Sie den Webhook vor dem Testen.
Aucun élément trouvé dans cette collection.|Ninguno element trouve en cette collection.
Version restaurée. Rechargement de la page…|Versión restauree. Rechargement de la página…
Échec du chargement de la présence de l'IA|Échec du chargement de la presence del IA
Décrivez à quoi sert cette sous-collection|Decrivez a quoi sert cette sous-collection
Être notifié lorsqu’une entrée est publiée|Être notifie lorsqu'une entree est publiee
Il vous reste {count} codes de sauvegarde.|Il usted reste {count} codes de sauvegarde.
Chargement Sie zuerst ein Update herunter.|Cargando Sie zuerst ein Update herunter.
Fermer l'assistant IA ({count} en attente)|Cerrar el assistant IA ({count} en attente)
Aucun (utiliser uniquement le pot de miel)|Ninguno (utiliser uniquement le pot de miel)
Erreur beim Enregistrer der Médiasdetails:|Error beim Guardar der Mediosdetails:
Ajoutez une source CMS ou une URL externe.|Ajoutez une source CMS o une URL externe.
Impossible de lire le document : {message}|No se puede lire le document : {message}
Construire à partir de CMS (première fois)|Construire a partir de CMS (premiere fois)
Erreur beim Chargement der Notifications:|Error beim Cargando der Notifications:
Aucune benutzerdefinierten Rôlen gefunden|Ninguna benutzerdefinierten Rolen gefunden
Êtes-vous sûr de vouloir supprimer ceci ?|¿Está seguro de vouloir eliminar ceci ?
{n} publication(s) depuis les collections|{n} publicación(s) desde les collections
Les e-mails sont enregistrés, pas envoyés|Les e-mails sont enregistres, pas envoyes
Erreur lors de la suppression de l'avatar|Error cuando de la suppression del avatar
par exemple, page de prix, annonce Amazon|par exemple, página de prix, annonce Amazon
Aucune Entrées entsprechen Ihren Filtren.|Ninguna Entradas entsprechen Ihren Filtren.
Déplacer cette entrée vers la corbeille ?|Deplacer cette entree hacia la corbeille ?
Aucune suggestion retournée pour ce champ|Ninguna suggestion retournee para ce champ
Action approuvée et exécutée avec succès.|Acción approuvee y executee con succes.
Aucune Médias zum Verschieben ausgewählt.|Ninguna Medios zum Verschieben ausgewählt.
Recommandations pour les étapes suivantes|Recommandations para les pasos suivantes
Choisissez d'abord un fichier multimédia|Elija de abord un archivo multimedia
Lien « Pour les agents » du pied de page|Lien « Pour les agents » du pied de página
Vous avez des modifications non publiées|Usted avez des modifications non publiees
1. Extrait du client Birkly (recommandé)|1. Extrait du client Birkly (recommande)
Paramètres konnten pas enregistré werden|Configuración konnten pas enregistre werden
Erreur lors de l'approbation de l'action|Error cuando del approbation del action
Désactivé. Activez dans l'onglet Outils.|Desactivado. Activez en el onglet Outils.
Erreur beim Actualiser des Utilisateurs:|Error beim Actualiser des Usuarios:
2FA für alle Admin-Utilisateur verlangen|2FA fur alle Admin-Usuario verlangen
Wählen Sie eine Fichier zum Hochcharger.|Wählen Sie eine Archivo zum Hochcharger.
Neuen Namen für diesen Dossier eingeben.|Neuen Namen fur diesen Carpeta eingeben.
Fichiers hochcharger ou Filtre anpassen.|Archisus hochcharger o Filtre anpassen.
Terminé — fermez lorsque vous êtes prêt|Termine — fermez cuando usted etes pret
Ou ajoutez cette règle de proxy inverse|Ou ajoutez cette regle de proxy inverse
Publier automatiquement les soumissions|Publicar automatiquement les soumissions
Description du contenu de démonstration|Descripción du contenido de demonstration
Erreur lors du chargement de l’activité|Error cuando du chargement de l'activite
De quel type d'agent avez-vous besoin ?|De quel type de agent avez-usted besoin ?
Choisissez d’abord un champ à modifier.|Elija d'abord un champ a editar.
Erreur beim Supprimer des Utilisateurs:|Error beim Eliminar des Usuarios:
Autoriser les robots IA dans robots.txt|Autoriser les robots IA en robots.txt
E-mail de connexion de l'administrateur|E-mail de connexion del administrateur
Anleitung: Site-Auslieferung einrichten|Anleitung: Sitio-Auslieferung einrichten
Délai d’expiration de session (minutes)|Delai d'expiration de session (minutes)
Impossible de charger les utilisateurs.|No se puede charger les utilisateurs.
Erreur beim Erstellen des Utilisateurs:|Error beim Erstellen des Usuarios:
Erreur lors du chargement de l'état 2FA|Error cuando du chargement del etat 2FA
Impossible de créer la connexion réseau|No se puede crear la connexion reseau
Linkfeld markieren et manuell kopieren.|Linkfeld markieren y manuell kopieren.
Erreur beim Enregistrer der Paramètres:|Error beim Guardar der Configuración:
Visibilité de l'agent de service mise à|Visibilite del agent de service mise a
Utilisateurs pouvant utiliser cet agent|Usuarios pouvant utiliser cet agent
Fichier zum Anzeigen ou Modifier wählen|Archivo zum Anzeigen o Editar wählen
Aide sur le mode d'approbation de l'IA|Ayuda en le mode de approbation del IA
Liste de refus sécurité des extensions|Liste de refus securite des extensions
Impossible de lire l'image : {message}|No se puede lire el image : {message}
Enregistrer les paramètres de stockage|Guardar les parametres de stockage
Danke! Ihr Feedback wurde übermittelt.|Danke! Ihr Feedback wurde ubermittelt.
Qu’est-ce qui fait votre réputation ? |Qu'est-ce qui fait su reputation ? 
Un nom que vous reconnaîtrez plus tard|Un nom que usted reconnaitrez plus tard
Actualiser depuis les fichiers du site|Actualiser desde les archisus du sitio
Aucune connexion e-mail pour le moment|Ninguna connexion e-mail para le moment
Nombre max. de tentatives de connexion|Nombrebre max. de tentatives de connexion
Votre rôle lorsque vous vous connectez|Su role cuando usted usted connectez
Découverte de l'IA (recherche et chat)|Decouverte del IA (recherche y chat)
Saisissez une clé API avant de tester.|Introduzca une cle API avant de tester.
Aucune version publiée pour le moment.|Ninguna version publiee para le moment.
Erreur beim Widerrufen des KI-Zugangs:|Error beim Widerrufen des KI-Zugangs:
En savoir plus sur les fonctionnalités|En savoir plus en les fonctionnalites
Document ajouté comme source de vérité|Document ajoute comme source de verite
Sélectionnez d'abord une connexion IA|Seleccione de abord une connexion IA
Quels formulaires peuvent être soumis|Quels formulaires peuvent etre soumis
Installer une extension depuis le Hub|Installer une extension desde le Hub
Aucun agent de service pour le moment|Ninguno agent de service para le moment
Chargement de l’état du compte cloud…|Cargando de l'etat du compte cloud…
Diese Médiasdatei wirklich supprimer?|Diese Mediosdatei wirklich eliminar?
Aucune différence entre ces versions.|Ninguna difference entre ces versions.
Revendications arriérées avec preuves|Revendications arrierees con preuves
Aucun e-mail configuré pour le moment|Ninguno e-mail configure para le moment
Erreur lors du chargement des données|Error cuando du chargement des donnees
{n} modèle(s) répétitif(s) détecté(s)|{n} modele(s) repetitif(s) detecte(s)
Ausgewählte Médiaselemente supprimer?|Ausgewählte Medioselemente eliminar?
Décrivez à quoi sert cette collection|Decrivez a quoi sert cette collection
Domaine réussi registriert!{role_msg}|Domaine reussi registriert!{role_msg}
2. Reverse proxy (facultatif, avancé)|2. Reverse proxy (facultatif, avance)
Wenn die Site Web bereits Inhalte hat|Wenn die Sitio Web bereits Inhalte hat
Ce que l'IA peut faire sur votre site|Ce que el IA peut faire en su sitio
Collection zur Sperrliste hinzugefügt|Colección zur Sperrliste hinzugefugt
par exemple, l'e-mail de mon site Web|par exemple, el e-mail de mon sitio Web
Modifier la connexion de l'assistant|Editar la connexion del assistant
Éditeur — suggérer des modifications|Editor — suggerer des modifications
Créer automatiquement des miniatures|Crear automatiquement des miniatures
Définissez l’URL de votre site dans |Definissez l'URL de su sitio en 
Résumez cette entrée en 2-3 phrases.|Resumez cette entree en 2-3 phrases.
Dossier konnte pas umbenannt werden:|Carpeta konnte pas umbenannt werden:
Compte cloud Birkly lié avec succès.|Compte cloud Birkly lie con succes.
Webhook konnte pas enregistré werden|Webhook konnte pas enregistre werden
Laisser le nouveau mot de passe vide|Laisser le nuevo mot de passe vide
Erreur beim Hochcharger der Fichier:|Error beim Hochcharger der Archivo:
Ou téléverser un fichier d’extension|Ou subir un archivo d'extension
Beschreiben Sie, was diese Rôle darf|Beschreiben Sie, was diese Role darf
Connexion AI mise à jour avec succès|Conexión AI mise a jour con succes
{n} page(s) principale(s) trouvée(s)|{n} página(s) principale(s) trouvee(s)
Merci! Votre soumission a été reçue!|Merci! Su soumission a ete recue!
Ressource zur Sperrliste hinzugefügt|Ressource zur Sperrliste hinzugefugt
Plugin konnte pas hinzugefügt werden|Plugin konnte pas hinzugefugt werden
Médias konnten pas gecharger werden:|Medios konnten pas gecharger werden:
Actions préparées pour votre examen.|Acciones preparees para su examen.
Aucune entrée publiée pour le moment|Ninguna entree publiee para le moment
Erreur beim Umbenennen des Dossiers:|Error beim Umbenennen des Carpetas:
Slow-Living-Journal + Demo-Site Web|Slow-Living-Registro + Demo-Sitio Web
Erreur beim Erstellen des Dossiers:|Error beim Erstellen des Carpetas:
Chargement du connecteur personnel…|Cargando du connecteur personnel…
Lire l'explication WebMCP de Google|Lire el explication WebMCP de Google
Le flux s'est terminé sans réponse.|Le flux se est termine sin reponse.
Accepter le risque et mettre à jour|Accepter le risque y mettre a jour
Qu'est-ce que le Guide de l'agent ?|Qu'est-ce que le Guía del agent ?
Liens croisés et santé de la source|Liens croises y sante de la source
par exemple, l'assistant de contenu|par exemple, el assistant de contenido
Analytique deaktiviert für {domain}|Analytique deaktiviert fur {domain}
Ungültige Antwort vom Update-Server|Ungultige Antwort vom Update-Server
Étapes de configuration de l'e-mail|Pasos de configuration del e-mail
Erreur beim Supprimer des Dossiers:|Error beim Eliminar des Carpetas:
Erreur beim Annuler der Invitation:|Error beim Cancelar der Invitation:
Erreur : Rôlenname ist erforderlich|Error : Rolenname ist erforderlich
Bienvenue sur votre tableau de bord|Bienvenue en su tableau de bord
Manuel (copier les URL et extraits)|Manuel (copier les URL y extraits)
Dossier konnte pas erstellt werden:|Carpeta konnte pas erstellt werden:
Dossier konnte pas gelöscht werden:|Carpeta konnte pas gelöscht werden:
Aucun modèle chargé pour le moment.|Ninguno modele charge para le moment.
Erreur lors de la mise à jour de la|Error cuando de la mise a jour de la
Erreur beim Actualiser der Domaine:|Error beim Actualiser der Domaine:
Supprimer ce contexte médiatique ?|Eliminar ce contexte mediatique ?
Gérer les domaines et l’analytique|Gerer les domaines y l'analytique
Saisissez une étiquette ou une URL|Introduzca une etiquette o une URL
Erreur beim Senden der Invitation:|Error beim Senden der Invitation:
Aucune connexion e-mail configurée|Ninguna connexion e-mail configuree
Langue du panneau d'administration|Langue du panneau de administration
Aucune redirection pour le moment.|Ninguna redirection para le moment.
Updates konnten pas geprüft werden|Updates konnten pas gepruft werden
Vérification Google Search Console|Verification Google Search Console
Rechercher collections et entrées…|Buscar collections y entrees…
Image de partage social par défaut|Image de partage social par defaut
Ihre Änderungen wurden enregistré.|Ihre Änderungen wurden enregistre.
Échec de l'approbation : {message}|Error al el approbation : {message}
Fiche de recherche dans Découverte|Fiche de recherche en Decouverte
Erreur beim Supprimer der Domaine:|Error beim Eliminar der Domaine:
Aucun site Web n'est encore suivi.|Ninguno sitio Web no est encore suivi.
Guide local (mcp-remote pas à pas)|Guía local (mcp-remote pas a pas)
Erreur beim Chargement der Médias:|Error beim Cargando der Medios:
Root / öffnet öffentliche Site Web|Root / öffnet öffentliche Sitio Web
Mot de passe de la base de données|Contraseña de la base de donnees
Delivery-Rôle für {host} actualisé|Delivery-Role fur {host} actualise
Erreur beim Hochcharger des Logos:|Error beim Hochcharger des Logos:
Wählen Sie zuerst eine Collection|Wählen Sie zuerst eine Colección
Catégorie · Toutes les catégories|Categorie · Todas les categories
Phare : outils WebMCP enregistrés|Phare : outils WebMCP enregistres
Version {version} · Publié {time}|Versión {version} · Publicado {time}
Impossible de charger les modèles|No se puede charger les modeles
Plugin zur Sperrliste hinzugefügt|Plugin zur Sperrliste hinzugefugt
Analytique aktiviert für {domain}|Analytique aktiviert fur {domain}
Comment l'IA se connecte à Birkly|Comment el IA se connecte a Birkly
Erreur bei Domaine-Registrierung:|Error bei Domaine-Registrierung:
Liste de vérification mise à jour|Liste de verification mise a jour
Statut de la partie de la machine|Estado de la partie de la machine
Chargement des connexions e-mail…|Cargando des connexions e-mail…
Fichiers durchsuchen & bearbeiten|Archisus durchsuchen & bearbeiten
Confirmer le nouveau mot de passe|Confirmar le nuevo mot de passe
Demande au visiteur de confirmer.|Demande au visitiour de confirmer.
Générer un profil complet (100 %)|Generer un profil complet (100 %)
Configurer les outils du site Web|Configurer les outils du sitio Web
Entrez le nom de l’automatisation|Entrez le nom de l'automatisation
Bild für Screenreader beschreiben|Bild fur Screenreader beschreiben
Primäre öffentliche URL actualisé|Primäre öffentliche URL actualise
Code de vérification (facultatif)|Code de verification (facultatif)
Régénérer les codes de sauvegarde|Regenerer les codes de sauvegarde
Taille max. de téléversement (Mo)|Taille max. de televersement (Mo)
Configurez votre guide de l'agent|Configurez su guide del agent
Wo ist Ihre öffentliche Site Web?|Wo ist Ihre öffentliche Sitio Web?
Mehr in unserer %1$s et den %2$s.|Mehr in unserer %1$s y den %2$s.
KI verbinden für erweiterte Hilfe|KI verbinden fur erweiterte Hilfe
{count} fichiers internes ignorés|{count} archisus internes ignores
Ajouter ou modifier une connexion|Añadir o editar une connexion
Erreur beim Supprimer der Médias:|Error beim Eliminar der Medios:
Propositions appliquées — {warns}|Propositions appliquees — {warns}
Ouvrir l’éditeur Guide de l’agent|Abrir l'editeur Guía de l'agent
Erreur beim Actualiser der Rôle:|Error beim Actualiser der Role:
Rôles pouvant utiliser cet agent|Roles pouvant utiliser cet agent
Saisissez REBUILD pour confirmer|Introduzca REBUILD para confirmer
Le guide de l'agent est en ligne|Le guide del agent est en ligne
Guide-Tipps für 7 Tage pausiert.|Guía-Tipps fur 7 Tage pausiert.
Guide-Fortschritt zurückgesetzt.|Guía-Fortschritt zuruckgesetzt.
Erreur beim Chargement der Rôle:|Error beim Cargando der Role:
Ajouter une sous-collection sous|Añadir une sous-collection sous
Erreur lors du rejet de l'action|Error cuando du rejet del action
Outils que cette page exposerait|Outils que cette página exposerait
Identité, positionnement et voix|Identite, positionnement y voix
Ihre Bibliothèque média ist leer|Ihre Bibliotheque media ist leer
Supprimer {count} sous-entrées ?|Eliminar {count} sous-entrees ?
Site de démonstration disponible|Sitio de demonstration disponible
Noch aucune Site-Packs installé.|Noch aucune Sitio-Packs installe.
Sélectionner une offre de remise|Selectionner une offre de remise
Erreur beim Supprimer des Logos:|Error beim Eliminar des Logos:
Erreur HTTP {status} : {message}|Error HTTP {status} : {message}
Toutes les suggestions acceptées|Todas les suggestions acceptees
Impossible de charger Découverte|No se puede charger Decouverte
Aucun journal des modifications.|Ninguno journal des modifications.
Git-Verfügbarkeit wird geprüft …|Git-Verfugbarkeit wird gepruft …
Ajouter des produits et services|Añadir des produits y services
Configuration en quelques étapes|Configuration en quelques pasos
Alias-Continuerleitung actualisé|Alias-Continuarleitung actualise
Authentification à deux facteurs|Authentification a deux facteurs
Analytique-Paramètres enregistré|Analytique-Configuración enregistre
Échec de la mise à jour du suivi|Error al la mise a jour du suivi
Définir le profil et le contexte|Definir le profil y le contexte
Exemple de niveau de sous-entrée|Exemple de niveau de sous-entree
Générer un projet de proposition|Generer un projet de proposition
Auto-hébergé / Docker (Dokploy)|Auto-heberge / Docker (Dokploy)
Einwilligungstext (Par défaut+)|Einwilligungstext (Par defaut+)
Modus der öffentlichen Site Web|Modus der öffentlichen Sitio Web
Screenshots ou Video (optional)|Screenshots o Video (optional)
Collectionen werden gecharger …|Colecciónen werden gecharger …
Article sur les pages de détail|Article en les páginas de detail
Aucune Release Notes verfügbar.|Ninguna Release Notes verfugbar.
Échec du chargement des modèles|Échec du chargement des modeles
Actualiser la liste de contrôle|Actualiser la liste de controle
Modifier le contexte médiatique|Editar le contexte mediatique
Accepter le risque et installer|Accepter le risque y installer
Saisir un mot de passe sécurisé|Saisir un mot de passe securise
Erreur beim Ändern der Sprache:|Error beim Ändern der Sprache:
Aucune Journauxinträge gefunden|Ninguna Journauxinträge gefunden
Aller à la gestion des webhooks|Aller a la gestion des webhooks
Version {version} ist verfügbar|Versión {version} ist verfugbar
Aucun Médiaselement ausgewählt.|Ninguno Medioselement ausgewählt.
Connecteur personnel désactivé.|Connecteur personnel desactive.
Ouvrir le site de démonstration|Abrir le sitio de demonstration
Erreur beim Erstellen der Rôle:|Error beim Erstellen der Role:
Créez votre première collection|Creez su premiere collection
Erreur beim Supprimer der Rôle:|Error beim Eliminar der Role:
Pourquoi l'avoir mis en place ?|Pourquoi el avoir mis en place ?
Mode de débogage du développeur|Mode de debogage du developpeur
Échec de la copie de la clé API|Error al la copie de la cle API
Aide sur les autorisations d'IA|Ayuda en les autorisations de IA
Impossible de supprimer l'agent|No se puede eliminar el agent
Passwörter stimmen pas überein|Passwörter stimmen pas uberein
Enregistrer la sous-collection|Guardar la sous-collection
Connexion AI créée avec succès|Conexión AI creee con succes
Créer un compte administrateur|Crear un compte administrateur
Adresse e-mail de l’expéditeur|Dirección de correo de l'expediteur
Modifier cette sous-collection|Editar cette sous-collection
Mise à jour des outils du site|Mise a jour des outils du sitio
Déplacer le champ vers le haut|Deplacer le champ hacia le haut
Guide-Fortschritt zurücksetzen|Guía-Fortschritt zurucksetzen
Ajoutez votre premier site Web|Ajoutez su premier sitio Web
Erreur : Formular pas gefunden|Error : Formular pas gefunden
Saisissez votre clé de licence|Introduzca su cle de licence
Aucune base de données requise|Ninguna base de donnees requise
Administrateur — accès complet|Administrador — acces complet
Ping IndexNow à la publication|Ping IndexNow a la publicación
Flatfile-Stockage (Par défaut)|Flatfile-Stockage (Par defaut)
Réponse du serveur non valide.|Reponse du serveur non valide.
Sélectionner tous les éléments|Selectionner tous les elements
Auf dieser Site Web widerrufen|Auf dieser Sitio Web widerrufen
À propos de PageSpeed Insights|À propos de PáginaSpeed Insights
Publier pour les systèmes d'IA|Publicar para les systemes de IA
Ajouter un premier utilisateur|Añadir un premier utilisateur
Ce que vous vendez et pour qui|Ce que usted vendez y para qui
Paramètres → Site-Auslieferung|Configuración → Sitio-Auslieferung
Starkes Mot de passe verlangen|Starkes Contraseña verlangen
Hosting-Paramètres enregistrer|Hosting-Configuración guardar
{count} modèle(s) répétitif(s)|{count} modele(s) repetitif(s)
Projet de proposition appliqué|Projet de proposition applique
Supprimer ce produit/service ?|Eliminar ce produit/service ?
Connexions de l'application IA|Conexións del application IA
Commencer à ajouter du contenu|Commencer a añadir du contenido
Ligne Sitemap: dans robots.txt|Ligne Sitiomap: en robots.txt
Continueren Entrée hinzufügen|Continuaren Entrada hinzufugen
Connexion - {{project}} Admin|Conexión - {{project}} Admin
Sie nutzen Version {version}.|Sie nutzen Versión {version}.
Copier dans le presse-papiers|Copiar en le presse-papiers
Données structurées (JSON-LD)|Donnees structurees (JSON-LD)
URL der öffentlichen Site Web|URL der öffentlichen Sitio Web
Aperçu de la meta description|Vista previa de la meta description
Voir le site de démonstration|Voir le sitio de demonstration
Suggérer des titres avec l’IA|Suggerer des titres con l'IA
Aucune connexion sélectionnée|Ninguna connexion selectionnee
Ouvrir les fichiers du projet|Abrir les archisus du projet
Journal d'activité et d'audit|Registro de activite y de audit
{count} entrées de collection|{count} entrees de collection
Envoyer l’e-mail d’invitation|Envoyer l'e-mail d'invitation
Essayez une question d'aperçu|Essayez une question de apercu
Test-Webhook réussi gesendet.|Test-Webhook reussi gesendet.
Générer une nouvelle clef API|Generer une nueva clef API
Code de formulaire intégrable|Code de formulaire integrable
Ajouter un nouvel utilisateur|Añadir un nouvel utilisateur
Noch aucune Plugins installé.|Noch aucune Plugins installe.
Einrichtung, Wege et Konzepte|Einrichtung, Wege y Konzepte
Hosting-Paramètres enregistré|Hosting-Configuración enregistre
Sélectionner des utilisateurs|Selectionner des utilisateurs
Utilisateur réussi actualisé!|Usuario reussi actualise!
In Paramètres → KI einrichten|In Configuración → KI einrichten
Améliorer le brouillon actuel|Ameliorer le brouillon actuel
Publié et à jour · v{version}|Publicado y a jour · v{version}
Remplacement d’URL enregistré|Remplacement d'URL enregistre
Enregistrer les modifications|Guardar les modifications
Déplacer le champ vers le bas|Deplacer le champ hacia le bas
Sélectionnez un type de champ|Seleccione un type de champ
Champ personnalisé mis à jour|Champ personnalise mis a jour
Aucun historique de versions.|Ninguno historique de versions.
Échec de la création du suivi|Error al la creation du suivi
Paramètres réussi enregistré!|Configuración reussi enregistre!
Échec de la suppression : %s|Error al la suppression : %s
modifications non publiées ?|modifications non publiees ?
Paramètres de l'API publique|Configuración del API publique
/mois · facturé annuellement|/mois · facture annuellement
Suivi de l'état d'avancement|Suivi del etat de avancement
Ouvrir les connexions e-mail|Abrir les connexions e-mail
Site-Pack-ZIP-Fichier wählen|Sitio-Pack-ZIP-Archivo wählen
À propos du Guide de l'agent|À propos du Guía del agent
retour à la page collections|retour a la página collections
Connecteur personnel activé.|Connecteur personnel active.
Page de tarification externe|Página de tarification externe
Erreur beim erneuten Senden:|Error beim erneuten Senden:
Envoyez un email de test à :|Envoyez un email de test a :
Chargement des autorisations|Cargando des autorisations
Sélectionner un transporteur|Selectionner un transporteur
Génération de suggestions...|Generation de suggestions...
Fichier modèle dans project/|Archivo modele en project/
Utilisateur réussi gelöscht!|Usuario reussi gelöscht!
Utilisateur réussi erstellt!|Usuario reussi erstellt!
Autorisations de l’extension|Permisos de l'extension
Suggérer à partir de sources|Suggerer a partir de sources
Chemin ou URL de destination|Chemin o URL de destination
{n} suggestion(s) à examiner|{n} suggestion(s) a examiner
Conserver le contenu de démo|Conserver le contenido de demo
Supprimer la sous-collection|Eliminar la sous-collection
Diese Médiasdatei supprimer?|Diese Mediosdatei eliminar?
Chargement des connexions...|Cargando des connexions...
Ausgewählte Médias gelöscht.|Ausgewählte Medios gelöscht.
Aucune Paramètres verfügbar.|Ninguna Configuración verfugbar.
Copié dans le presse-papier!|Copie en le presse-papier!
Licences auf dieser Site Web|Licences auf dieser Sitio Web
Enregistrer échec: {message}|Guardar echec: {message}
Guide-Paramètres enregistré.|Guía-Configuración enregistre.
Chargement des approbations…|Cargando des approbations…
Télécharger agent-guide.json|Descargar agent-guide.json
In Birkly (project/-Dossier)|In Birkly (project/-Carpeta)
Afficher l'URL du connecteur|Mostrar el URL du connecteur
Règles uniquement (pas d'IA)|Regles uniquement (pas de IA)
Remote-Prüfung pas verfügbar|Remote-Prufung pas verfugbar
Confirmez votre mot de passe|Confirmez su mot de passe
Afficher les enregistrements|Mostrar les enregistrements
Déposez les collections ici|Deposez les collections ici
ou commencer avec un modèle|ou commencer con un modele
Aide sur le nom d’affichage|Ayuda en le nom d'affichage
Guide de l'agent mis à jour|Guía del agent mis a jour
Sélectionner un fournisseur|Selectionner un fournisseur
Saisissez un nom de groupe.|Introduzca un nom de groupe.
Préférences de notification|Preferences de notification
Guide de l'agent non publié|Guía del agent non publie
Fichiers page dans project/|Archisus página en project/
Générer à partir de sources|Generer a partir de sources
Ajouter une sous-collection|Añadir une sous-collection
Hosting (aus Site delivery)|Hosting (aus Sitio delivery)
Outils de site Web (WebMCP)|Outils de sitio Web (WebMCP)
Médiaselement pas gefunden.|Medioselement pas gefunden.
Ouvrir le menu d'assistance|Abrir le menu de assistance
Masquer l'URL du connecteur|Ocultar el URL du connecteur
Guide — Tipp für diese Page|Guía — Tipp fur diese Página
System réussi neu aufgebaut|System reussi neu aufgebaut
Chargement des utilisateurs|Cargando des utilisateurs
Utilisateur non disponible.|Usuario non disponible.
Bienvenue dans Birkly Admin|Bienvenue en Birkly Admin
Votre contenu se trouve ici|Su contenido se trouve ici
Dossier „{name}“ supprimer?|Carpeta „{name}“ eliminar?
Ajouter un identifiant HTTP|Añadir un identifiant HTTP
Format de réponse inattendu|Format de reponse inattendu
Vérification Bing Webmaster|Verification Bing Webmaster
Automatisierung enregistrer|Automatisierung guardar
Apparence dans la recherche|Apparence en la recherche
Modifier la sous-collection|Editar la sous-collection
Les plus récents en premier|Les plus recents en premier
Site-Root (ohne index.html)|Sitio-Root (ohne index.html)
Aucune connexion disponible|Ninguna connexion disponible
Limite d’écriture par heure|Limite d'ecriture par heure
Meta description par défaut|Meta description par defaut
Canal · Toutes les versions|Canal · Todas les versions
Gérer toutes les extensions|Gerer toutes les extensions
Aucune sous-entrée trouvée.|Ninguna sous-entree trouvee.
JSON non valide dans {path}|JSON non valide en {path}
Créez votre premier élément|Creez su premier element
Site-Pack aus ZIP installé|Sitio-Pack aus ZIP installe
Aucune proposition générée|Ninguna proposition generee
Erste Collection erstellen|Erste Colección erstellen
Valeurs par défaut du site|Valeurs par defaut du sitio
Sélectionner un relecteur…|Selectionner un relecteur…
Hôte de la base de données|Hote de la base de donnees
Choisissez la connexion AI|Elija la connexion AI
Retour aux entrées actives|Volver aux entrees actives
Suggérer des produits (IA)|Suggerer des produits (IA)
Rechercher des extensions…|Buscar des extensions…
Copier l'URL du connecteur|Copiar el URL du connecteur
Nombre d'avis (facultatif)|Nombrebre de avis (facultatif)
Votre connecteur personnel|Su connecteur personnel
Agent de service supprimé.|Agent de service supprime.
Flux de travail recommandé|Flux de travail recommande
Paramètres SEO enregistrés|Configuración SEO enregistres
Exemple de niveau d'entrée|Exemple de niveau de entree
Port de la base de données|Port de la base de donnees
Brouillon à partir du plan|Borrador a partir du plan
Actions de sous-collection|Acciones de sous-collection
Déplacer vers la corbeille|Deplacer hacia la corbeille
E-mail par défaut : {name}|E-mail par defaut : {name}
Source de données ajoutée.|Source de donnees ajoutee.
Étape 2 : vérifiez le code|Paso 2 : verifiez le code
Désactiver la fonction 2FA|Desactiver la fonction 2FA
NOK · Couronne norvégienne|NOK · Couronne norvegienne
Enregistrer la redirection|Guardar la redirection
Site-Pack-Fichiers ersetzt|Sitio-Pack-Archisus ersetzt
Chargement des connexions…|Cargando des connexions…
Suggérer des liens croisés|Suggerer des liens croises
Aucune Médias ausgewählt.|Ninguna Medios ausgewählt.
Chargement de l’état 2FA…|Cargando de l'etat 2FA…
Vérifier les mises à jour|Verifier les mises a jour
Balise de modèle (opt-in)|Balise de modele (opt-in)
Créer un agent de service|Crear un agent de service
Le téléversement a échoué|Le televersement a echoue
Fichiers verwalten unter |Archisus verwalten unter 
Aucune Angebote gefunden.|Ninguna Angebote gefunden.
Paramètres de l’extension|Configuración de l'extension
Chargement des révisions…|Cargando des revisions…
Chargement des modèles 3D|Cargando des modeles 3D
Créer une sous-collection|Crear une sous-collection
Sous-collection imbriquée|Sous-collection imbriquee
Confirmer le mot de passe|Confirmar le mot de passe
Diesen Webhook supprimer?|Diesen Webhook eliminar?
Médiasdetails enregistré.|Mediosdetails enregistre.
Nom de la sous-collection|Nombre de la sous-collection
Optional Description Help|Optional Descripción Help
Publication déjà en cours|Publicación deja en cours
Dossier réussi umbenannt.|Carpeta reussi umbenannt.
Téléchargement de {name}…|Telechargement de {name}…
Tout ce qu’il faut savoir|Tout ce qu'il faut savoir
Domaine réussi actualisé!|Domaine reussi actualise!
Test de connexion réussi.|Test de connexion reussi.
Accepter et mettre à jour|Accepter y mettre a jour
Communication et conseils|Communication y conseils
Suggérer des réclamations|Suggerer des reclamations
Modifier cette collection|Editar cette collection
Échec de l’enregistrement|Error al l'enregistrement
Chargement des extensions|Cargando des extensions
Paramètres de publication|Configuración de publicación
Enregistrer la collection|Guardar la collection
Ajouter un lien de source|Añadir un lien de source
Serverseitig (Par défaut)|Serverseitig (Par defaut)
Ajouter une accréditation|Añadir une accreditation
Site-Pack réussi installé|Sitio-Pack reussi installe
URL ou ZIP de l’extension|URL o ZIP de l'extension
Sélectionnez au moins une|Seleccione au moins une
Exploration et découverte|Exploration y decouverte
Une erreur s’est produite|Une erreur s'est produite
Aucune raison spécifiée !|Ninguna raison specifiee !
Dernière exécution : {at}|Derniere execution : {at}
Gesamte Activité anzeigen|Gesamte Actividad anzeigen
Aucun avis pour le moment|Ninguno avis para le moment
Conditions : (facultatif)|Condicións : (facultatif)
Utilisateur aktualisieren|Usuario aktualisieren
par exemple, les réponses|par exemple, les reponses
Prüfung auf Updates échec|Prufung auf Updates echec
Nom de la base de données|Nombre de la base de donnees
Mises à jour SEO publiées|Mises a jour SEO publiees
Aucune collection trouvée|Ninguna collection trouvee
Eigenes Muster hinzufügen|Eigenes Muster hinzufugen
Enregistrer l'identifiant|Guardar el identifiant
Bienvenue dans Birkly ! 🎉|Bienvenue en Birkly ! 🎉
Aller au flux d’activité|Aller au flux d'activite
Unenregistrée Änderungen|Unenregistree Änderungen
Contenu de démonstration|Contenido de demonstration
Principales réclamations|Principales reclamations
Connecter l'assistant IA|Connecter el assistant IA
Nouvelle sous-collection|Nueva sous-collection
La connexion est activée|La connexion est activee
Description facultative.|Descripción facultative.
Glisser pour réorganiser|Glisser para reorganiser
Résumé avec grande image|Resume con grande image
Suggérer à partir du CMS|Suggerer a partir du CMS
Tri · Prix : décroissant|Tri · Prix : decroissant
Aller au journal d’audit|Aller au journal d'audit
 pour un aperçu complet.| para un apercu complet.
Aucune connexion trouvée|Ninguna connexion trouvee
Autoriser l'accès public|Autoriser el acces public
Version {version} läuft.|Versión {version} läuft.
Sélectionner une section|Selectionner une section
Ajouter une source d'URL|Añadir une source de URL
Mise à jour : v{version}|Mise a jour : v{version}
Domaine réussi gelöscht!|Domaine reussi gelöscht!
Mises à jour disponibles|Mises a jour disponibles
Restaurer cette entrée ?|Restaurar cette entree ?
Aucun utilisateur trouvé|Ninguno utilisateur trouve
Enregistrer la connexion|Guardar la connexion
Réclamation introuvable.|Reclamation introuvable.
Administrateur seulement|Administrador seulement
Utilisateurs individuels|Usuarios individuels
Médias werden gecharger…|Medios werden gecharger…
Réinitialiser par défaut|Reinitialiser par defaut
Bibliothèque SOAP CLIENT|Bibliotheque SOAP CLIENT
Ajouter une connexion AI|Añadir une connexion AI
Dépublier cette entrée ?|Despublicar cette entree ?
le site de documentation|le sitio de documentation
Titre du site par défaut|Título du sitio par defaut
Chargement de l’activité|Cargando de l'activite
Cliquez pour télécharger|Haga clic para descargar
Publier les mises à jour|Publicar les mises a jour
Publication et diffusion|Publicación y diffusion
Créer une automatisation|Crear une automatisation
Exemple au niveau entrée|Exemple au niveau entree
3. Tester et enregistrer|3. Tester y guardar
Collections non groupées|Colecciones non groupees
KI-Connexions widerrufen|KI-Conexións widerrufen
Aucun assistant connecté|Ninguno assistant connecte
Dossier réussi erstellt.|Carpeta reussi erstellt.
Ajouter des preuves CMS|Añadir des preuves CMS
Reconstruire le système|Reconstruire le systeme
Aucune activité récente|Ninguna activite recente
Ajouter un justificatif|Añadir un justificatif
Vérifier tous les liens|Verifier tous les liens
La clé API est requise.|La cle API est requise.
Installer depuis le Hub|Installer desde le Hub
Saisir pour rechercher…|Saisir para buscar…
Relations et écosystème|Relations y ecosysteme
Aide URL de l’extension|Ayuda URL de l'extension
SEK · Couronne suédoise|SEK · Couronne suedoise
Types de contenu variés|Types de contenido varies
Base de donnéesbenutzer|Base de donneesbenutzer
Mettre à jour le groupe|Mettre a jour le groupe
Aucune activité trouvée|Ninguna activite trouvee
Guide de l'agent publié|Guía del agent publie
Basculer en plein écran|Basculer en plein ecran
Öffentliche Site öffnen|Öffentliche Sitio öffnen
Modèle de tarification,|Modele de tarification,
Titre pour la recherche|Título para la recherche
Affichage et espacement|Affichage y espacement
Ajouter une sous-entrée|Añadir une sous-entree
Redirection enregistrée|Redirection enregistree
Réclamations et preuves|Reclamations y preuves
Pièce d'identité copiée|Piece de identite copiee
Saisir le nom du modèle|Saisir le nom du modele
L’e-mail semble correct|L'e-mail semble correct
Plateforme d'évaluation|Plateforme de evaluation
Modifier la redirection|Editar la redirection
Mettre à jour l'élément|Mettre a jour el element
Échec de la suppression|Error al la suppression
Domaines et hébergement|Domaines y hebergement
Ordre de tri par défaut|Ordre de tri par defaut
Agent de service créé !|Agent de service cree !
Intégration du site Web|Integration du sitio Web
Medium réussi gelöscht.|Medium reussi gelöscht.
Récit (lire en premier)|Recit (lire en premier)
Description Synthétique|Descripción Synthetique
Mettre à jour le statut|Mettre a jour le statut
Plugin réussi actualisé|Plugin reussi actualise
Aucune Felder definiert|Ninguna Felder definiert
Aucun modèle répertorié|Ninguno modele repertorie
Rechercher des entrées…|Buscar des entrees…
2. Öffentliche Site Web|2. Öffentliche Sitio Web
Historique des versions|Historial des versions
Échec de la duplication|Error al la duplication
Générée automatiquement|Generee automatiquement
Ajouter une redirection|Añadir une redirection
URL du webhook d'alerte|URL du webhook de alerte
Ajouter un nouveau rôle|Añadir un nuevo role
Publier cette entrée ?|Publicar cette entree ?
Lien d’invitation prêt|Lien d'invitation pret
USD · Dollar américain|USD · Dollar americain
Paramètres durchsuchen|Configuración durchsuchen
Réponse du fournisseur|Reponse du fournisseur
Paramètres enregistrer|Configuración guardar
Événements utilisateur|Eventos utilisateur
Télécharger des médias|Descargar des medias
Rôle réussi actualisé!|Role reussi actualise!
Ajouter la réclamation|Añadir la reclamation
Titre de la collection|Título de la collection
Pied de page one-liner|Pied de página one-liner
Connexions IA ouvertes|Conexións IA ouvertes
Utilisateur eincharger|Usuario eincharger
Sélectionner l'élément|Selectionner el element
API Javascript avancée|API Javascript avancee
Utilisateur bearbeiten|Usuario bearbeiten
Modifier l’utilisateur|Editar l'utilisateur
Quittez le plein écran|Quittez le plein ecran
Plugin réussi {action}|Plugin reussi {action}
Toutes les collections|Todas les collections
Ajouter une source CMS|Añadir une source CMS
Entrée veröffentlichen|Entrada veröffentlichen
Autorisations requises|Permisos requises
En attente de révision|Pendiente de revision
Événements de sécurité|Eventos de securite
Ignorer pour l’instant|Ignorer para l'instant
Modifier la collection|Editar la collection
Plugin réussi installé|Plugin reussi installe
Collection enregistrée|Colección enregistree
Guide de configuration|Guía de configuration
Aucune aktiven Plugins|Ninguna aktiven Plugins
Masquer l'introduction|Ocultar el introduction
Créer Gérer le contenu|Crear Gerer le contenido
Site-Pack installieren|Sitio-Pack installieren
Guide étape par étape |Guía paso par paso 
Version Birkly Running|Versión Birkly Running
Analytique du site Web|Analytique du sitio Web
Utilisateur hinzufügen|Usuario hinzufugen
Publication désactivée|Publicación desactivee
Offres et réclamations|Offres y reclamations
In Dossier verschieben|In Carpeta verschieben
Conseils personnalisés|Conseils personnalises
Vérifier et surveiller|Verifier y surveiller
Chargement en cours...|Cargando en cours...
Plugin réussi gelöscht|Plugin reussi gelöscht
Tri · Plus téléchargés|Tri · Plus telecharges
Aucune Médias gefunden|Ninguna Medios gefunden
Vues de l'API publique|Vues del API publique
Chargement des rôles…|Cargando des roles…
Gérer dans les outils|Gerer en les outils
Redirection supprimée|Redirection supprimee
Configurer Découverte|Configurer Decouverte
Toutes les ressources|Todas les ressources
Erreur beim Abmelden:|Error beim Abmelden:
Accepter et installer|Accepter y installer
Dernier déclenchement|Dernier declenchement
Choisissez la méthode|Elija la methode
Ausführungsprotokolle|Ausfuhrungsprotokolle
Entrez en plein écran|Entrez en plein ecran
Ajouter une condition|Añadir une condition
Démarrer la migration|Demarrer la migration
Attente d'approbation|Attente de approbation
Canal · Stable + bêta|Canal · Stable + beta
Toutes les catégories|Todas les categories
Entrées de collection|Entradas de collection
Etapes d'installation|Etapes de installation
Extensions installées|Extensions installees
Mettre à jour le rôle|Mettre a jour le role
Relevé de réclamation|Releve de reclamation
Installer l’extension|Installer l'extension
Site de démonstration|Sitio de demonstration
Alle Entrées anzeigen|Alle Entradas anzeigen
Événements de contenu|Eventos de contenido
Installation vérifiée|Installation verifiee
Publier & enregistrer|Publicar & guardar
Utilisateur supprimer|Usuario eliminar
En attente de l'IA...|Pendiente del IA...
Utilisateur erstellen|Usuario erstellen
Collection hinzufügen|Colección hinzufugen
Ouvrir le JSON public|Abrir le JSON public
Logo réussi entfernt!|Logo reussi entfernt!
Rôle réussi gelöscht!|Role reussi gelöscht!
Erste Rôle hinzufügen|Erste Role hinzufugen
Champ de résumé court|Champ de resume court
Copier l’extrait meta|Copiar l'extrait meta
Fermer l'assistant IA|Cerrar el assistant IA
Rôle réussi erstellt!|Role reussi erstellt!
Niveau d’autorisation|Niveau d'autorisation
sitemap.xml dynamique|sitiomap.xml dynamique
Type · Tous les types|Type · Todos les types
Continuer bearbeiten|Continuar bearbeiten
Supprimer une langue|Eliminar une langue
Plugin-Autorisations|Plugin-Permisos
Continuer à modifier|Continuar a editar
Collection parente :|Colección parente :
Update-Prüfung échec|Update-Prufung echec
Inclure dans Sitemap|Inclure en Sitiomap
Sélectionner le type|Selectionner le type
Configuration guidée|Configuration guidee
Sources de la vérité|Sources de la verite
Dernier cours publié|Dernier cours publie
Brouillon enregistré|Borrador enregistre
Aucun élément trouvé|Ninguno element trouve
Dernière mise à jour|Derniere mise a jour
Vérification complet|Verification complet
Mises à jour système|Mises a jour systeme
Aucune Notifications|Ninguna Notifications
Suggérer des entrées|Suggerer des entrees
Des différenciateurs|Des differenciateurs
Médias hochgecharger|Medios hochgecharger
Collections récentes|Colecciones recentes
Assistant de contenu|Assistant de contenido
8 caractères minimum|8 caracteres minimum
Produits et services|Productos y services
Aucune Unter-Entrées|Ninguna Unter-Entradas
Code de vérification|Code de verification
Configurer avec l’IA|Configurer con l'IA
Ajouter une relation|Añadir une relation
Modèles de recherche|Modeles de recherche
Modèle de collection|Modele de collection
Qu’est-ce que le CMS|Qu'est-ce que le CMS
Configurer l'e-mail |Configurer el e-mail 
Site delivery öffnen|Sitio delivery öffnen
Constructeur de site|Constructeur de sitio
Termes évités (JSON)|Termes evites (JSON)
Copier l'identifiant|Copiar el identifiant
Créer une collection|Crear une collection
Paramètres du compte|Configuración du compte
Entrée de collection|Entrada de collection
Utilisateur et Rôlen|Usuario y Rolen
Description complète|Descripción complete
Schlüssel aktivieren|Schlussel aktivieren
Sélectionner un type|Selectionner un type
Récemment mis à jour|Recemment mis a jour
Accusé de préversion|Accuse de preversion
Domaines enregistrés|Domaines enregistres
Site Web, Outils de |Sitio Web, Outils de 
Effacer la sélection|Effacer la selection
Webhooks Description|Webhooks Descripción
Produits ou services|Productos o services
Nouvelle collection |Nueva collection 
(Termes privilégiés)|(Termes privilegies)
Approuvé et exécuté|Approuve y execute
Connexion supprimée|Conexión supprimee
Schéma Organization|Schema Organization
Entwurf enregistrer|Entwurf guardar
Sous-entrée parente|Sous-entree parente
Paramètres → Projet|Configuración → Projet
Qualité d’image (%)|Qualite d'image (%)
Nouvelle discussion|Nueva discussion
Données de l’entrée|Donnees de l'entree
Noch aucune Entrées|Noch aucune Entradas
Sécurité de session|Seguridad de session
Date d’installation|Date d'installation
Ajouter un document|Añadir un document
Médiaseinstellungen|Medioseinstellungen
Collection schützen|Colección schutzen
Aucune Beschreibung|Ninguna Beschreibung
Nouvelle collection|Nueva collection
Cliquez pour copier|Haga clic para copier
Nom de l'expéditeur|Nombre del expediteur
Copier le permalien|Copiar le permalien
Widgets d’extension|Widgets d'extension
Bienvenue à nouveau|Bienvenue a nuevo
J'ai scanné le code|J'ai scanne le code
Tous les évènements|Todos les evenements
Résumer la collecte|Resumer la collecte
Extrait meta et SEO|Extrait meta y SEO
URL d'image externe|URL de image externe
Ajouter un agent IA|Añadir un agent IA
Suggestion acceptée|Suggestion acceptee
Détails : {message}|Details : {message}
Vom Inhalt zur Site|Vom Inhalt zur Sitio
Sprache enregistrer|Sprache guardar
Améliorer l'extrait|Ameliorer el extrait
Actions de révision|Acciones de revision
Nom de la connexion|Nombre de la connexion
Invitation acceptée|Invitation acceptee
Brouillon vs publié|Borrador vs publie
Clé Captcha du site|Cle Captcha du sitio
Gérer dans Réglages|Gerer en Reglages
Aperçu du permalien|Vista previa du permalien
Clé secrète Captcha|Cle secrete Captcha
Préréglages rapides|Prereglages rapides
Collections sources|Colecciones sources
Développement local|Developpement local
Mot de passe actuel|Contraseña actuel
Tipp für diese Page|Tipp fur diese Página
Journal uniquement|Registro uniquement
Site Web-Identität|Sitio Web-Identität
Installation échec|Installation echec
Produit ou service|Producto o service
Entrée mise à jour|Entrada mise a jour
Publier une entrée|Publicar une entree
Entrée enregistrer|Entrada guardar
Supprimer l’entrée|Eliminar l'entree
Unter-Collectionen|Unter-Colecciónen
Vérifiez à nouveau|Verifiez a nuevo
Modifier l'élément|Editar el element
Modifier le champ…|Editar le champ…
Prix : décroissant|Prix : decroissant
Gérer dans Publier|Gerer en Publicar
Hub-Anfrage échec.|Hub-Anfrage echec.
Status enregistrer|Status guardar
Profil enregistrer|Profil guardar
Übermittlung échec|Übermittlung echec
Utilisation prévue|Utilisation prevue
Retour zum Angebot|Volver zum Angebot
Équipe et sécurité|Équipe y securite
Continuer im Guide|Continuar im Guía
Unbekannter Erreur|Unbekannter Error
Webhook-Test échec|Webhook-Test echec
Devise d’affichage|Devise d'affichage
Détail de l’entrée|Detail de l'entree
Aperçu du contexte|Vista previa du contexte
Annuler la réponse|Cancelar la reponse
Ajouter une source|Añadir une source
Modifier le groupe|Editar le groupe
Gestion de contenu|Gestion de contenido
Ajouter une action|Añadir une action
Améliorer la copie|Ameliorer la copie
Type d'identifiant|Type de identifiant
Chargement du Hub…|Cargando du Hub…
Total des éléments|Total des elements
Admin & Paramètres|Admin & Configuración
Bientôt disponible|Bientot disponible
Entrez les détails|Entrez les details
Bibliothèque média|Bibliotheque media
Événements système|Eventos systeme
Modèles répétitifs|Modeles repetitifs
Supprimer le champ|Eliminar le champ
· Modèle : {model}|· Modele : {model}
Médias durchsuchen|Medios durchsuchen
Neues Mot de passe|Neues Contraseña
Détails de l’offre|Details de l'offre
Assistant intégré|Assistant integre
Entrée principale|Entrada principale
Tout sélectionner|Tout selectionner
Letzte Activitéen|Letzte Actividaden
Accès aux données|Acces aux donnees
Désactiver la 2FA|Desactiver la 2FA
De la médiathèque|De la mediatheque
Référence interne|Reference interne
Modifier le titre|Editar le titre
Enregistrer échec|Guardar echec
Copier la clé API|Copiar la cle API
Prix de référence|Prix de reference
Envoyer à nouveau|Envoyer a nuevo
Champ de résumé :|Champ de resume :
Ajouter une image|Añadir une image
Échec de l’action|Error al l'action
Fichier auswählen|Archivo auswählen
Fermer le panneau|Cerrar le panneau
Schéma des champs|Schema des champs
Nom de votre site|Nombre de su sitio
Méthode de saisie|Methode de saisie
Où il est hébergé|Ou il est heberge
Erreur de serveur|Error de serveur
Suggérer un titre|Suggerer un titre
Plugin hinzufügen|Plugin hinzufugen
Documentation API|Documentación API
Première ecriture|Premiere ecriture
Robots par défaut|Robots par defaut
Choisir un modèle|Choisir un modele
Connexion réussie|Conexión reussie
Entrée enregistré|Entrada enregistre
Toutes les fiches|Todas les fiches
Générer avec l’IA|Generer con l'IA
Diffusion du site|Diffusion du sitio
Langue du contenu|Langue du contenido
Pas encore généré|Pas encore genere
So funktioniert’s|So funktioniert's
Gérer les entrées|Gerer les entrees
Supprimer le rôle|Eliminar le role
Site-Auslieferung|Sitio-Auslieferung
Activé sur {date}|Activado en {date}
Brève description|Breve description
Collection wählen|Colección wählen
Alle Fichiers in |Alle Archisus in 
L'IA réfléchit...|L'IA reflechit...
Activité anzeigen|Actividad anzeigen
Tri · Mieux notés|Tri · Mieux notes
Modifier le rôle|Editar le role
Le plus fréquent|Le plus frequent
Toute l'activité|Toute el activite
Collection créée|Colección creee
Résumer l'entrée|Resumer el entree
Entrée supprimée|Entrada supprimee
Activité récente|Actividad recente
Créer et inviter|Crear y inviter
Plus téléchargés|Plus telecharges
Auto Publication|Auto Publicación
Chargement échec|Cargando echec
Éditeur d’entrée|Editor d'entree
Créer un webhook|Crear un webhook
Projet supprimer|Projet eliminar
Mode d'affichage|Mode de affichage
Santé du système|Sante du systeme
Actions groupées|Acciones groupees
Rapide et simple|Rapide y simple
Tous les statuts|Todos les statuts
Exercice à trous|Exercice a trous
vous@example.com|usted@example.com
Contenu connecté|Contenido connecte
Ajouter un agent|Añadir un agent
Migration échec:|Migration echec:
Ce que sait l'IA|Ce que sait el IA
Options avancées|Options avancees
Fournisseur d'IA|Fournisseur de IA
Refuser l'action|Refuser el action
Update verfügbar|Update verfugbar
Alertes de santé|Alertes de sante
Créer un élément|Crear un element
Site-Utilisateur|Sitio-Usuario
Invitation échec|Invitation echec
Détail canonique|Detail canonique
Version actuelle|Versión actuelle
Guide de l’agent|Guía de l'agent
Lern-Site öffnen|Lern-Sitio öffnen
Nom de référence|Nombre de reference
Entrée commentée|Entrada commentee
Entrée à réviser|Entrada a reviser
Aller au contenu|Aller au contenido
Entrées récentes|Entradas recentes
téléchargements|telechargements
Confidentialité|Privacidad
Paramètres → IA|Configuración → IA
Préparation des|Preparation des
Actions rapides|Acciones rapides
Rôle des agents|Role des agents
Rôlenverwaltung|Rolenverwaltung
A quoi ça sert?|A quoi ca sert?
Projetübersicht|Projetubersicht
Édition avancée|Édition avancee
Nouvelle valeur|Nueva valeur
Balises on-page|Balises on-página
Rôle bearbeiten|Role bearbeiten
Libellé de prix|Libelle de prix
Créer un groupe|Crear un groupe
Tableau de bord|Panel
Ressource média|Ressource media
API et webhooks|API y webhooks
Fichier du site|Archivo du sitio
Médias externes|Medios externes
Team & Sécurité|Team & Seguridad
Demande annulée|Demande annulee
Liste du marché|Liste du marche
Paramètres → KI|Configuración → KI
Action rejetée.|Acción rejetee.
Je réfléchis...|Je reflechis...
Entrée erstellt|Entrada erstellt
Réseaux Sociaux|Reseaux Sociaux
Utilisateurmenü|Usuariomenu
Gérer tous les|Gerer tous les
Tous les types|Todos les types
Copier le slug|Copiar le slug
Aucun résultat|Ninguno resultat
Automatisation|Automatización
Nouvel élément|Nouvel element
Étape Suivante|Paso Suivante
Rôle supprimer|Role eliminar
Retour au chat|Volver au chat
Nouveau groupe|Nuevo groupe
Clé de licence|Cle de licence
Adresse e-mail|Dirección de correo
Retour zum Hub|Volver zum Hub
Pièce jointe :|Piece jointe :
Aperçu du logo|Vista previa du logo
Collection CMS|Colección CMS
Entrée publiée|Entrada publiee
Action requise|Acción requise
Dossier actuel|Carpeta actuel
Nom de l'agent|Nombre del agent
Copier le code|Copiar le code
Ouvrir la page|Abrir la página
Tous supports:|Todos supports:
Download échec|Download echec
Rôle erstellen|Role erstellen
ID de l’entrée|ID de l'entree
Copier le lien|Copiar le lien
ex. Expédition|ex. Expedition
Entrée parente|Entrada parente
Schéma WebSite|Schema WebSitio
Nom et prénoms|Nombre y prenoms
Publier le SEO|Publicar le SEO
Connecter l'IA|Connecter el IA
Upload échec.|Upload echec.
Rôle CMS fixe|Role CMS fixe
Site-Pack-ZIP|Sitio-Pack-ZIP
Tout est prêt|Tout est pret
Sélectionner…|Selectionner…
Messages clés|Messages cles
Médiasdetails|Mediosdetails
100 $ et plus|100 $ y plus
Autorisations|Permisos
Neuer Dossier|Neuer Carpeta
Page publique|Página publique
Connexions AI|Conexións AI
Icône (emoji)|Icone (emoji)
Nom du groupe|Nombre du groupe
Concepts clés|Concepts cles
Avertissement|Advertencia
KI-Connexions|KI-Conexións
Entrée publié|Entrada publie
Delivery-Rôle|Delivery-Role
Titre du site|Título du sitio
Nouveau champ|Nuevo champ
Upload échec:|Upload echec:
Anfrage échec|Anfrage echec
Erreurbericht|Errorbericht
s’ouvre dans|s'ouvre dans
Exhaustivité|Exhaustivite
Enregistrer…|Guardar…
Type d’agent|Type d'agent
Nom du champ|Nombre du champ
Nom de l’API|Nombre de l'API
Nom officiel|Nombre officiel
Update échec|Update echec
Utilisateurs|Usuarios
Mises à jour|Mises a jour
Guide öffnen|Guía öffnen
Collectionen|Colecciónen
Dépublier le|Despublicar le
Entrée créée|Entrada creee
Mot de passe|Contraseña
Copier l’URL|Copiar l'URL
Rôle modifié|Role modifie
URL de l'API|URL del API
SEO dépublié|SEO depublie
Rôles&#xA0;:|Roles&#xA0;:
Neue Fichier|Neue Archivo
Site-Sprache|Sitio-Sprache
Connexion AI|Conexión AI
Pack de site|Pack de sitio
Aucune aktiv|Ninguna aktiv
Clientseitig|Clienteeseitig
Page öffnen|Página öffnen
Dossiername|Carpetaname
Désignation|Designation
Chargement…|Cargando…
Nom du rôle|Nombre du role
Prix · Tous|Prix · Todos
Rôle fixe :|Role fixe :
Clé d'icône|Cle de icone
Sous-entrée|Sous-entree
Déconnexion|Cierre de sesión
Nom affiché|Nombre affiche
Déconnecter|Deconnecter
Utilisateur|Usuario
Hébergement|Hebergement
Clé secrète|Cle secrete
sélectionné|selectionne
Enregistrer|Guardar
Mieux notés|Mieux notes
Votre avis|Su avis
Entrée CMS|Entrada CMS
SEO publié|SEO publie
Découverte|Decouverte
Événements|Eventos
Brouillons|Borradors
Désactivée|Desactivadoe
Chargement|Cargando
Publier le|Publicar le
Catégories|Categories
Slug copié|Slug copie
Désactiver|Desactiver
Odmietnuté|Odmietnute
Enregistré|Enregistre
Paramètres|Configuración
Site-Packs|Sitio-Packs
Recommandé|Recommande
Facultatif|Opcional
Idéal pour|Ideal pour
En attente|Pendiente
URL copiée|URL copiee
Représente|Represente
Connexions|Conexións
% terminé|% termine
Corbeille|Papelera
Rôle fixe|Role fixe
Confirmer|Confirmar
3. Aperçu|3. Vista previa
Continuer|Continuar
Médias-ID|Medios-ID
Non réglé|No regle
Dépublier|Despublicar
Référence|Reference
Brouillon|Borrador
Tri · Nom|Tri · Nombre
Restaurer|Restaurar
Réessayez|Reessayez
Pokročilé|Pokročile
Révisions|Revisions
Catégorie|Categorie
Désactivé|Desactivado
Titre A-Z|Título A-Z
Capacités|Capacites
Supprimer|Eliminar
Titre Z-A|Título Z-A
Identité|Identite
Approuvé|Approuve
Révoquée|Revoquee
éléments|elements
Sécurité|Seguridad
Créé par|Cree par
Fichiers|Archisus
Erreur :|Error :
Activité|Actividad
Dernière|Derniere
Afficher|Mostrar
Site Web|Sitio Web
Versions|Versións
Connecté|Connecte
Unpublié|Unpublie
Modifier|Editar
Réussite|Reussitio
Clé API|Cle API
Générer|Generer
entrées|entrees
terminé|termine
Système|Systeme
Publier|Publicar
Annuler|Cancelar
Inactif|Inactivo
Ajouter|Añadir
Erreur:|Error:
Modèles|Modeles
Détails|Details
Réviser|Reviser
Produit|Producto
Expirée|Expiree
Contenu|Contenido
Ouvrir|Abrir
ModèlE|ModelE
Statut|Estado
Marché|Marche
Vidéos|Videos
Activé|Activado
Rejeté|Rejete
Médias|Medios
Publié|Publicado
Erreur|Error
Fermer|Cerrar
Modèle|Modele
Retour|Volver
Aperçu|Vista previa
entrée|entree
Résumé|Resume
Actif|Activo
Dépôt|Depot
Grâce|Grace
Copié|Copie
Aucun|Ninguno
Rôlen|Rolen
Sites|Sitios
Titre|Título
Thème|Theme
Aide|Ayuda
Créé|Cree
Bêta|Beta
Tous|Todos
Rôle|Role
Nom|Nombre
TXT;
    $pairs = [];
    foreach (explode("\n", trim($raw)) as $line) {
        if ($line === '' || !str_contains($line, '|')) { continue; }
        [$fr, $es] = explode('|', $line, 2);
        $pairs[] = [$fr, $es];
    }
    usort($pairs, static fn ($a, $b) => strlen($b[0]) <=> strlen($a[0]));
    return $pairs;
}

function p85_es_translate_fr(string $fr): string
{
    $store = [];
    $s = preg_replace_callback('/(\\{\\{[^{}]+\\}\\}|\\{[a-zA-Z0-9_.]+\\}|%[sd]|@[a-zA-Z0-9_.:-]+|<[^>]+>)/', static function ($m) use (&$store) {
        $key = "\x00T" . count($store) . "\x00";
        $store[$key] = $m[0];
        return $key;
    }, $fr) ?? $fr;
    foreach (p85_es_fr_to_es_pairs() as [$from, $to]) {
        if ($from !== '' && str_contains($s, $from)) {
            $s = str_replace($from, $to, $s);
        }
    }
    return strtr($s, $store);
}
