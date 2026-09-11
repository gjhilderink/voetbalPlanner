/**
 * Waar komt die ongelezen-telling vandaan?
 *
 * De Berichten-tab telt `chatConversations.unreadByUser[<jouw e-mail>]` op over
 * alle gesprekken waar je in staat. De chatlijst bouwt zichzelf uit heel andere
 * bronnen op, dus een telling kan naar een gesprek wijzen dat nergens in de
 * lijst staat. Dit script legt die twee naast elkaar: het noemt elk gesprek met
 * een openstaande teller en zegt erbij waaróm de app hem wel of niet kan tonen.
 *
 * Gebruik:
 *   set GOOGLE_APPLICATION_CREDENTIALS=C:\pad\naar\service-account.json
 *   node diagnose-unread.js iemand@voorbeeld.nl
 *   node diagnose-unread.js iemand@voorbeeld.nl --messages
 *
 * De sleutel haal je eenmalig op in de Firebase-console onder
 * Projectinstellingen → Service accounts → Nieuwe privésleutel genereren.
 * Bewaar hem buiten deze map; hij geeft volledige toegang tot het project.
 */

const admin = require('firebase-admin');

const PROJECT_ID = 'voetbalplanner-b4062';

const email = (process.argv[2] || '').trim().toLowerCase();
const metBerichten = process.argv.includes('--messages');

if (!email || email.startsWith('--')) {
  console.error('Gebruik: node diagnose-unread.js <e-mailadres> [--messages]');
  process.exit(1);
}

if (!process.env.GOOGLE_APPLICATION_CREDENTIALS) {
  console.error(
    'GOOGLE_APPLICATION_CREDENTIALS ontbreekt — wijs hem naar een service-account-sleutel\n' +
      'van project ' + PROJECT_ID + ' (Firebase-console → Projectinstellingen → Service accounts).',
  );
  process.exit(1);
}

admin.initializeApp({
  credential: admin.credential.applicationDefault(),
  projectId: PROJECT_ID,
});

const db = admin.firestore();

/** 'unreadByUser' is een map met e-mailadressen als sleutel. */
function telVoor(data, adres) {
  const raw = data.unreadByUser;
  if (!raw || typeof raw !== 'object') return 0;
  const v = raw[adres];
  return typeof v === 'number' ? v : 0;
}

function tijd(ts) {
  if (!ts) return '—';
  try {
    return ts.toDate().toISOString().replace('T', ' ').slice(0, 16);
  } catch (_) {
    return String(ts);
  }
}

/**
 * Kan de chatlijst dit gesprek tonen, en zo nee, waarom niet?
 *
 * De lijst bestaat uit vier losse secties: de elftallen uit de app-state, de
 * groepen waar je lid van bent, de staffgroepen uit de Laravel-API en de leden
 * van je huidige elftal. Alles wat daar buiten valt telt wel mee maar is
 * onbereikbaar — dat is precies wat we zoeken.
 */
async function beoordeel(convId, data) {
  const type = (data.type || '').toString();
  const teamId = (data.teamId || '').toString();
  const deelnemer =
    Array.isArray(data.participantIds) && data.participantIds.includes(email);

  const opmerkingen = [];

  if (!deelnemer) {
    opmerkingen.push(
      'je staat NIET in participantIds — geteld via de elftal-stream, niet via de deelnemers-stream',
    );
  }

  if (type === 'staffgroep' || type === 'staffgroup') {
    opmerkingen.push(
      'staffgroep: heeft geen teamId, dus geen badge naast het elftal; de rij komt uit de Laravel-API ' +
        '(GetStaffGroups) en verdwijnt zodra je uit de groep bent gehaald',
    );
  }

  if (type === 'group' || convId.startsWith('group_')) {
    const naam = convId.replace(/^group_/, '');
    let bestaat = false;
    let lid = false;
    try {
      let snap = await db.collection('chatGroups').doc(naam).get();
      let g = snap.exists ? snap.data() : null;
      if (!g) {
        const q = await db
          .collection('chatGroups')
          .where('name', '==', naam)
          .limit(1)
          .get();
        if (!q.empty) g = q.docs[0].data();
      }
      if (g) {
        bestaat = true;
        lid = Array.isArray(g.members) && g.members.includes(email);
      }
    } catch (_) {}

    if (!bestaat) {
      opmerkingen.push('de bijbehorende chatGroups-doc bestaat niet (meer) — geen rij mogelijk');
    } else if (!lid) {
      opmerkingen.push('je staat niet in chatGroups.members — de groep staat niet in je lijst');
    }
  }

  if (type === 'direct' || (!type && convId.includes('_'))) {
    opmerkingen.push(
      'directe chat: de rij komt uit de ledenlijst van je HUIDIGE elftal. Zit de tegenpartij ' +
        'daar niet in (ander elftal, uit de club, of geen lid), dan is er geen rij',
    );
  }

  if (!teamId && type !== 'staffgroep' && type !== 'staffgroup') {
    opmerkingen.push('geen teamId — komt niet in de badge naast een elftal terecht');
  }

  return { type, teamId, deelnemer, opmerkingen };
}

async function main() {
  console.log('Project : ' + PROJECT_ID);
  console.log('Adres   : ' + email);
  console.log('');

  // Eén keer alles ophalen en zelf filteren: unreadByUser is een map en daar
  // kun je niet op "> 0" query'en zonder per e-mailadres een index te hebben.
  const snap = await db.collection('chatConversations').get();

  const rijen = [];
  let totaal = 0;

  for (const doc of snap.docs) {
    const data = doc.data() || {};
    const aantal = telVoor(data, email);
    if (aantal <= 0) continue;
    totaal += aantal;
    rijen.push({ id: doc.id, data, aantal });
  }

  if (rijen.length === 0) {
    console.log('Geen enkel gesprek met een openstaande teller voor dit adres.');
    console.log('Zag je toch een getal, kijk dan of het e-mailadres exact klopt:');
    console.log('de teller staat op het adres waarmee je in de app bent ingelogd.');
    return;
  }

  console.log('Verwachte telling op de Berichten-tab: ' + totaal);
  console.log('Gevonden gesprekken: ' + rijen.length);
  console.log('');

  rijen.sort((a, b) => b.aantal - a.aantal);

  for (const rij of rijen) {
    const { id, data, aantal } = rij;
    const oordeel = await beoordeel(id, data);

    console.log('─'.repeat(72));
    console.log(aantal + '  ' + id);
    console.log('   type        : ' + (oordeel.type || '—'));
    console.log('   teamId      : ' + (oordeel.teamId || '—'));
    console.log('   titel       : ' + (data.title || '—'));
    console.log('   laatste     : ' + (data.lastMessage || '—'));
    console.log('   wanneer     : ' + tijd(data.lastMessageAt));
    console.log('   deelnemer   : ' + (oordeel.deelnemer ? 'ja' : 'NEE'));

    if (oordeel.opmerkingen.length === 0) {
      console.log('   zichtbaar   : hoort gewoon in de lijst te staan');
    } else {
      for (const o of oordeel.opmerkingen) {
        console.log('   let op      : ' + o);
      }
    }

    if (metBerichten) {
      const msgs = await db
        .collection('chatMessages')
        .where('conversationId', '==', id)
        .orderBy('createdAt', 'desc')
        .limit(3)
        .get();
      if (msgs.empty) {
        console.log('   berichten   : geen — de teller wijst naar niets (opgeruimd bericht?)');
      } else {
        console.log('   berichten   :');
        for (const m of msgs.docs) {
          const d = m.data();
          console.log(
            '      ' + tijd(d.createdAt) + '  ' + (d.senderName || d.senderId || '?') +
              ': ' + (d.text || '').slice(0, 60),
          );
        }
      }
    }
  }

  console.log('─'.repeat(72));
}

main().then(
  () => process.exit(0),
  (e) => {
    console.error('Mislukt: ' + (e && e.message ? e.message : e));
    process.exit(1);
  },
);
