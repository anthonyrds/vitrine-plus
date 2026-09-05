import { ArrowLeft } from "lucide-react";
import { Link } from "react-router-dom";

import SEO from "../components/SEO";

const articles = [
  {
    title: "Article 1 — Organisateur",
    content: (
      <>
        <p>
          La société exploitant l'activité Vitrine+, ci-après dénommée
          « l'Organisateur », organise une opération promotionnelle intitulée
          « Le Grand + ».
        </p>

        <p>
          L'opération est accessible depuis le site internet de Vitrine+,
          notamment à l'adresse :
        </p>

        <p className="font-semibold text-[#080808]">
          https://vitrineplus.fr/le-grand-plus
        </p>

        <p>
          Les informations légales complètes relatives à l'Organisateur sont
          disponibles sur la page « Mentions légales » du site Vitrine+.
        </p>
      </>
    ),
  },
  {
    title: "Article 2 — Objet de l'opération",
    content: (
      <>
        <p>
          Le Grand + est une opération promotionnelle organisée par Vitrine+
          permettant à une entreprise participante d'être sélectionnée chaque
          mois afin de bénéficier d'une refonte complète et gratuite de son
          site internet.
        </p>

        <p>
          L'objectif de l'opération est de permettre à une entreprise de
          bénéficier d'une présence digitale plus claire, plus moderne et plus
          adaptée à son activité.
        </p>

        <p>
          La sélection du bénéficiaire comporte une part aléatoire selon les
          modalités définies dans le présent règlement.
        </p>
      </>
    ),
  },
  {
    title: "Article 3 — Durée",
    content: (
      <>
        <p>
          Le Grand + est organisé sur une base mensuelle.
        </p>

        <p>
          Chaque période de participation correspond à un mois civil, sauf
          modification exceptionnelle annoncée par l'Organisateur.
        </p>


        <p>
          L'Organisateur se réserve la possibilité de modifier les dates d'une
          période de participation, de la prolonger, de la suspendre ou de
          l'annuler notamment en cas de nécessité technique, de force majeure
          ou de circonstance indépendante de sa volonté.
        </p>

        <p>
          Toute modification substantielle sera portée à la connaissance des
          participants par tout moyen approprié.
        </p>
      </>
    ),
  },
  {
    title: "Article 4 — Conditions de participation",
    content: (
      <>
        <p>
          Le Grand + est ouvert aux entreprises, entrepreneurs, indépendants,
          commerçants, artisans, associations et professionnels disposant
          d'une activité légalement exercée.
        </p>

        <p>
          La participation est réservée aux personnes majeures.
        </p>

        <p>Pour participer, le candidat doit :</p>

        <ul>
          <li>exercer une activité professionnelle ou associative ;</li>
          <li>
            renseigner les informations demandées dans le formulaire de
            participation ;
          </li>
          <li>fournir des informations exactes et à jour ;</li>
          <li>accepter le présent règlement ;</li>
          <li>valider sa participation via le formulaire prévu à cet effet.</li>
        </ul>

        <p>
          La participation au Grand + est entièrement gratuite.
        </p>

        <p>
          Aucun achat, abonnement, commande ou souscription à une prestation
          Vitrine+ n'est nécessaire pour participer.
        </p>
      </>
    ),
  },
  {
    title: "Article 5 — Modalités de participation",
    content: (
      <>
        <p>
          Pour participer au Grand +, le candidat doit remplir le formulaire
          disponible sur la page dédiée du site Vitrine+.
        </p>

        <p>Les informations demandées peuvent notamment comprendre :</p>

        <ul>
          <li>nom et prénom ;</li>
          <li>nom de l'entreprise ;</li>
          <li>adresse électronique professionnelle ;</li>
          <li>numéro de téléphone ;</li>
          <li>secteur d'activité ;</li>
          <li>adresse du site internet, lorsqu'il en existe un ;</li>
          <li>
            principale difficulté ou problématique rencontrée concernant la
            présence digitale de l'entreprise.
          </li>
        </ul>

        <p>
          Le candidat doit également accepter le présent règlement.
        </p>

        <p>
          Une case distincte permet au participant, s'il le souhaite, de
          consentir à recevoir des communications commerciales de Vitrine+.
        </p>

        <p>
          Cette dernière case est facultative et n'a aucune incidence sur la
          participation au Grand +.
        </p>
      </>
    ),
  },
  {
    title: "Article 6 — Validité des participations",
    content: (
      <>
        <p>
          Une seule participation par entreprise et par période mensuelle est
          autorisée.
        </p>

        <p>L'Organisateur pourra exclure toute participation :</p>

        <ul>
          <li>incomplète ;</li>
          <li>manifestement frauduleuse ;</li>
          <li>comportant des informations volontairement fausses ;</li>
          <li>
            effectuée au moyen d'un procédé automatisé ou frauduleux ;
          </li>
          <li>ne respectant pas le présent règlement ;</li>
          <li>
            présentant un comportement susceptible de perturber le
            fonctionnement normal de l'opération.
          </li>
        </ul>

        <p>
          L'Organisateur pourra demander au participant toute information
          permettant de vérifier son identité ou son activité.
        </p>

        <p>
          Toute participation irrégulière pourra être annulée.
        </p>
      </>
    ),
  },
  {
    title: "Article 7 — Désignation du bénéficiaire",
    content: (
      <>
        <p>
          À l'issue de chaque période mensuelle de participation, une
          entreprise participante sera sélectionnée par tirage au sort
          aléatoire parmi les participations valides enregistrées pendant la
          période concernée.
        </p>

        <p>
          Le tirage au sort sera réalisé par l'Organisateur ou sous son
          contrôle, selon une procédure permettant d'assurer une sélection
          aléatoire.
        </p>

        <p>
          L'Organisateur pourra conserver une trace du processus de sélection
          afin d'en assurer la traçabilité.
        </p>

        <p>
          Un ou plusieurs participants suppléants pourront également être
          désignés.
        </p>
      </>
    ),
  },
  {
    title: "Article 8 — Information du bénéficiaire",
    content: (
      <>
        <p>
          L'entreprise sélectionnée sera contactée par l'Organisateur aux
          coordonnées communiquées lors de sa participation.
        </p>

        <p>
          Le bénéficiaire disposera d'un délai de <strong>7 jours calendaires</strong>{" "}
          à compter de la première prise de contact pour confirmer qu'il
          accepte le bénéfice du Grand +.
        </p>

        <p>
          À défaut de réponse dans ce délai, ou en cas d'impossibilité de
          vérifier les informations communiquées, l'Organisateur pourra
          désigner un participant suppléant.
        </p>

        <p>
          L'Organisateur ne pourra être tenu responsable d'une impossibilité
          de contacter le bénéficiaire résultant d'informations erronées ou
          incomplètes communiquées lors de la participation.
        </p>
      </>
    ),
  },
  {
    title: "Article 9 — Lot",
    content: (
      <>
        <p>
          Le bénéficiaire du Grand + reçoit une <strong>refonte complète et
          gratuite de son site internet</strong>, réalisée par Vitrine+.
        </p>

        <p>Le lot peut notamment comprendre :</p>

        <ul>
          <li>réflexion et direction artistique ;</li>
          <li>conception ou refonte de l'interface ;</li>
          <li>UX / UI design ;</li>
          <li>développement du site ;</li>
          <li>adaptation responsive aux différents écrans ;</li>
          <li>structuration des pages ;</li>
          <li>intégration des contenus fournis par le bénéficiaire ;</li>
          <li>optimisation technique de base ;</li>
          <li>fondations SEO ;</li>
          <li>optimisation des parcours de conversion.</li>
        </ul>

        <p>
          Le périmètre exact du projet sera défini avec le bénéficiaire avant
          le début de la réalisation.
        </p>
      </>
    ),
  },
  {
    title: "Article 10 — Limites du lot",
    content: (
      <>
        <p>
          Le Grand + offre une prestation de création ou de refonte de site
          internet.
        </p>

        <p>
          Sauf accord écrit contraire, le lot ne comprend pas automatiquement :
        </p>

        <ul>
          <li>l'achat d'un nom de domaine ;</li>
          <li>les frais d'hébergement ;</li>
          <li>les abonnements à des services tiers ;</li>
          <li>les licences de logiciels ou de contenus payants ;</li>
          <li>la création de photographies professionnelles ;</li>
          <li>la production vidéo ;</li>
          <li>les campagnes publicitaires ;</li>
          <li>les dépenses liées à des outils ou services externes ;</li>
          <li>les prestations de référencement payant ;</li>
          <li>les prestations réalisées par des tiers.</li>
        </ul>

        <p>
          Les éventuels services supplémentaires non compris dans le périmètre
          initial pourront faire l'objet d'un devis séparé.
        </p>

        <p>
          Le bénéficiaire ne sera toutefois pas obligé d'accepter une
          prestation complémentaire pour bénéficier du lot.
        </p>
      </>
    ),
  },
  {
    title: "Article 11 — Contenus fournis par le bénéficiaire",
    content: (
      <>
        <p>
          Le bénéficiaire reste responsable des éléments qu'il fournit à
          Vitrine+ pour la réalisation du projet.
        </p>

        <p>Il garantit notamment disposer des droits nécessaires sur :</p>

        <ul>
          <li>les textes ;</li>
          <li>photographies ;</li>
          <li>vidéos ;</li>
          <li>logos ;</li>
          <li>illustrations ;</li>
          <li>marques ;</li>
          <li>documents ;</li>
          <li>autres éléments transmis.</li>
        </ul>

        <p>
          Le bénéficiaire garantit que l'utilisation de ces éléments par
          Vitrine+ dans le cadre du projet ne porte pas atteinte aux droits de
          tiers.
        </p>
      </>
    ),
  },
  {
    title: "Article 12 — Réalisation du projet",
    content: (
      <>
        <p>
          Après confirmation du bénéficiaire, Vitrine+ prendra contact avec
          celui-ci afin de définir les besoins et le périmètre de la refonte.
        </p>

        <p>
          La réalisation sera effectuée selon un calendrier défini entre les
          parties.
        </p>

        <p>
          Le bénéficiaire s'engage à collaborer raisonnablement avec Vitrine+
          et à transmettre les éléments nécessaires à la réalisation du projet
          dans des délais permettant son bon déroulement.
        </p>

        <p>
          Un retard important dans la transmission des éléments nécessaires
          pourra entraîner un décalage du calendrier de réalisation.
        </p>
      </>
    ),
  },
  {
    title: "Article 13 — Absence d'échange du lot",
    content: (
      <>
        <p>Le lot ne peut pas être :</p>

        <ul>
          <li>échangé contre sa valeur monétaire ;</li>
          <li>remboursé ;</li>
          <li>cédé à un tiers ;</li>
          <li>
            transformé en prestation différente à la demande du bénéficiaire.
          </li>
        </ul>

        <p>
          La valeur commerciale de la prestation offerte peut varier selon le
          projet et sa complexité.
        </p>
      </>
    ),
  },
  {
    title: "Article 14 — Communication du bénéficiaire",
    content: (
      <>
        <p>
          Sauf opposition légitime du bénéficiaire ou accord contraire,
          Vitrine+ pourra annoncer publiquement le nom de l'entreprise
          bénéficiaire du Grand + afin de communiquer sur l'opération.
        </p>

        <p>
          Toute publication utilisant l'identité, le logo, les photographies,
          les témoignages ou les contenus du bénéficiaire à des fins de
          communication commerciale devra faire l'objet d'un accord approprié
          lorsque celui-ci est nécessaire.
        </p>

        <p>
          L'entreprise bénéficiaire pourra notamment être présentée comme :
        </p>

        <blockquote>
          « Bénéficiaire du Grand + de Vitrine+ »
        </blockquote>

        <p>
          Aucune participation ne sera conditionnée à l'acceptation d'une
          communication commerciale.
        </p>
      </>
    ),
  },
  {
    title: "Article 15 — Données personnelles",
    content: (
      <>
        <p>
          Les données personnelles recueillies dans le cadre du Grand + sont
          traitées par Vitrine+ afin notamment de :
        </p>

        <ul>
          <li>gérer les participations ;</li>
          <li>vérifier leur validité ;</li>
          <li>organiser le tirage au sort ;</li>
          <li>contacter le bénéficiaire ;</li>
          <li>assurer la réalisation du lot ;</li>
          <li>répondre aux demandes des participants ;</li>
          <li>assurer la sécurité de l'opération.</li>
        </ul>

        <p>
          Les données nécessaires à la gestion de la participation sont
          traitées dans le cadre de l'organisation de l'opération.
        </p>

        <p>
          Les données ne sont pas utilisées à des fins de prospection
          commerciale sur la seule base de la participation au Grand +.
        </p>

        <p>
          Le participant peut, lorsqu'une base légale l'exige, consentir
          séparément à recevoir des communications commerciales de Vitrine+.
        </p>

        <p>
          Ce consentement est facultatif et peut être retiré à tout moment.
        </p>
      </>
    ),
  },
  {
    title: "Article 16 — Droits sur les données",
    content: (
      <>
        <p>
          Conformément à la réglementation applicable en matière de protection
          des données personnelles, les participants disposent notamment,
          selon les conditions prévues par la réglementation, d'un droit :
        </p>

        <ul>
          <li>d'accès ;</li>
          <li>de rectification ;</li>
          <li>d'effacement ;</li>
          <li>de limitation du traitement ;</li>
          <li>d'opposition ;</li>
          <li>
            de retrait du consentement lorsque le traitement repose sur
            celui-ci ;
          </li>
          <li>
            de portabilité lorsque ce droit est applicable.
          </li>
        </ul>

        <p>
          Pour exercer leurs droits ou obtenir des informations
          complémentaires concernant leurs données personnelles, les
          participants peuvent contacter Vitrine+ à l'adresse :
        </p>

        <p className="font-semibold text-[#080808]">
          vitrineplus@hotmail.com
        </p>

        <p>
          Ils peuvent également introduire une réclamation auprès de la
          Commission nationale de l'informatique et des libertés (CNIL).
        </p>
      </>
    ),
  },
  {
    title: "Article 17 — Conservation des données",
    content: (
      <>
        <p>
          Les données personnelles sont conservées pendant une durée
          proportionnée aux finalités pour lesquelles elles sont traitées.
        </p>

        <p>
          Les données nécessaires à la gestion de l'opération pourront
          notamment être conservées pendant la durée nécessaire au traitement
          des participations, à la désignation du bénéficiaire et à la gestion
          des éventuelles contestations.
        </p>

        <p>
          Lorsque le participant a consenti à recevoir des communications
          commerciales, les données correspondantes pourront être conservées
          conformément aux règles applicables à la prospection commerciale et
          jusqu'au retrait de son consentement ou pour la durée prévue par la
          politique de confidentialité de Vitrine+.
        </p>
      </>
    ),
  },
  {
    title: "Article 18 — Responsabilité",
    content: (
      <>
        <p>L'Organisateur ne pourra être tenu responsable :</p>

        <ul>
          <li>d'une interruption du site internet ;</li>
          <li>d'une défaillance technique ;</li>
          <li>d'un problème de connexion internet ;</li>
          <li>d'un dysfonctionnement du réseau ;</li>
          <li>
            d'une perte de données résultant d'un événement indépendant de sa
            volonté ;
          </li>
          <li>
            de toute participation non reçue pour une raison technique ;
          </li>
          <li>
            d'informations erronées communiquées par un participant.
          </li>
        </ul>

        <p>
          L'Organisateur pourra prendre toute mesure nécessaire afin de
          préserver l'intégrité et le bon fonctionnement de l'opération.
        </p>
      </>
    ),
  },
  {
    title: "Article 19 — Modification ou annulation",
    content: (
      <>
        <p>
          Vitrine+ se réserve le droit de modifier, suspendre ou annuler Le
          Grand + en cas de force majeure, de difficulté technique, de fraude,
          de circonstance exceptionnelle ou de toute situation rendant
          impossible la poursuite normale de l'opération.
        </p>

        <p>
          Dans la mesure du possible, les participants seront informés de
          toute modification importante.
        </p>

        <p>
          Aucune modification ne pourra avoir pour effet de priver
          arbitrairement un bénéficiaire déjà valablement désigné du lot qui
          lui a été attribué.
        </p>
      </>
    ),
  },
  {
    title: "Article 20 — Fraude",
    content: (
      <>
        <p>
          Toute tentative de fraude ou de manipulation de l'opération pourra
          entraîner l'exclusion immédiate du participant concerné.
        </p>

        <p>
          L'Organisateur se réserve le droit de prendre toute mesure nécessaire
          à l'encontre des personnes ayant tenté de contourner les règles de
          l'opération.
        </p>
      </>
    ),
  },
  {
    title: "Article 21 — Acceptation du règlement",
    content: (
      <>
        <p>
          La participation au Grand + implique l'acceptation pleine et entière
          du présent règlement.
        </p>

        <p>
          Le règlement est accessible gratuitement sur le site internet de
          Vitrine+.
        </p>

        <p>
          Le participant reconnaît avoir pu en prendre connaissance avant de
          participer.
        </p>
      </>
    ),
  },
  {
    title: "Article 22 — Réclamations",
    content: (
      <>
        <p>
          Toute réclamation relative à l'opération devra être adressée à
          Vitrine+ par écrit à l'adresse :
        </p>

        <p className="font-semibold text-[#080808]">
          vitrineplus@hotmail.com
        </p>

        <p>Les réclamations devront, dans la mesure du possible, préciser :</p>

        <ul>
          <li>l'identité du participant ;</li>
          <li>les coordonnées utilisées lors de la participation ;</li>
          <li>l'objet précis de la réclamation ;</li>
          <li>
            tout élément permettant d'en vérifier le bien-fondé.
          </li>
        </ul>
      </>
    ),
  },
  {
    title: "Article 23 — Droit applicable",
    content: (
      <>
        <p>
          Le présent règlement est soumis au droit français.
        </p>

        <p>
          Tout litige relatif à l'interprétation ou à l'exécution du présent
          règlement fera l'objet d'une recherche préalable de résolution
          amiable.
        </p>

        <p>
          À défaut de résolution amiable, le litige sera soumis aux
          juridictions compétentes conformément aux règles de droit commun.
        </p>
      </>
    ),
  },
  {
    title: "Article 24 — Entrée en vigueur",
    content: (
      <>
        <p>
          Le présent règlement entre en vigueur à compter de sa publication sur
          le site internet de Vitrine+.
        </p>

        <p>
          Il pourra être mis à jour lorsque cela sera nécessaire.
        </p>

        <p>
          La version applicable est celle publiée sur le site au moment de la
          participation, sous réserve des modifications ultérieures
          régulièrement portées à la connaissance des participants.
        </p>
      </>
    ),
  },
];

export default function ReglementGrandPlus() {
  return (
    <>
      <SEO
        title="Règlement du Grand + | Vitrine+"
        description="Consultez le règlement officiel de l'opération Le Grand + organisée par Vitrine+."
        canonical="/reglement-grand-plus"
      />

      <main className="min-h-screen bg-[#f4f4f1] text-[#080808]">
        {/* HERO */}
        <section className="bg-[#080808] px-6 pb-20 pt-36 text-white lg:px-8 lg:pb-24 lg:pt-44">
          <div className="mx-auto max-w-5xl">
            <Link
              to="/le-grand-plus"
              className="inline-flex items-center gap-2 text-sm font-semibold !text-white/50 transition hover:!text-white"
            >
              <ArrowLeft size={16} />
              Retour au Grand +
            </Link>

            <p className="mt-12 text-xs font-bold uppercase tracking-[0.28em] text-[#c8a45d]">
              Le Grand +
            </p>

            <h1 className="display mt-5 max-w-4xl text-5xl font-extrabold leading-[0.92] tracking-[-0.055em] sm:text-7xl lg:text-8xl">
              Règlement de
              <br />
              l'opération.
            </h1>

            <p className="mt-8 max-w-2xl text-base leading-7 text-white/50 sm:text-lg">
              Retrouvez ci-dessous l'intégralité des modalités de participation
              à l'opération promotionnelle Le Grand +.
            </p>

            <div className="mt-8 inline-flex rounded-full border border-white/10 bg-white/[0.04] px-4 py-2 text-xs font-semibold !text-white/50">
              Version en vigueur au 5 septembre 2026
            </div>
          </div>
        </section>

        {/* CONTENU */}
        <section className="px-6 py-16 lg:px-8 lg:py-24">
          <div className="mx-auto max-w-5xl">
            <div className="rounded-[2rem] bg-white p-7 shadow-[0_20px_80px_rgba(0,0,0,.06)] sm:p-10 lg:p-14">
              <div className="space-y-14">
                {articles.map((article) => (
                  <article
                    key={article.title}
                    className="border-b border-black/10 pb-14 last:border-0 last:pb-0"
                  >
                    <h2 className="display text-2xl font-extrabold leading-tight tracking-[-0.03em] sm:text-3xl">
                      {article.title}
                    </h2>

                    <div className="mt-6 max-w-3xl space-y-5 text-sm leading-7 text-black/55 sm:text-base">
                      {article.content}
                    </div>
                  </article>
                ))}
              </div>
            </div>

            {/* CTA */}
            <div className="mt-10 rounded-[2rem] bg-[#080808] p-8 text-white sm:p-12">
              <p className="text-xs font-bold uppercase tracking-[0.24em] text-[#c8a45d]">
                Le Grand +
              </p>

              <h2 className="display mt-4 max-w-3xl text-4xl font-extrabold leading-[0.95] tracking-[-0.05em] sm:text-5xl">
                Vous avez pris connaissance du règlement ?
              </h2>

              <p className="mt-5 max-w-xl text-sm leading-7 text-white/45">
                Si vous souhaitez participer, retournez sur la page du Grand +
                et remplissez simplement le formulaire.
              </p>

              <Link
                to="/le-grand-plus#participer"
                className="mt-8 inline-flex items-center gap-3 rounded-full bg-white px-6 py-4 text-sm font-bold !text-[#080808] transition hover:bg-[#c8a45d] hover:!text-[#080808]"
              >
                Participer au Grand +
                <ArrowLeft
                  size={17}
                  className="rotate-180 text-[#080808]"
                />
              </Link>
            </div>
          </div>
        </section>
      </main>
    </>
  );
}