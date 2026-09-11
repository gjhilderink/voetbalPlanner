/**
 * Rekent de ongelezen-tellers na tegen de werkelijke berichten en corrigeert ze.
 *
 * Zolang het toestel van de afzender de teller ophoogde, kon die uit de pas
 * gaan lopen: een half gelukte schrijfactie, een deelnemerslijst die van de
 * telefoon kwam, of een gesprek waar de app het verkeerde id voor gebruikte.
 * Het gevolg was een getal op de Berichten-tab dat naar niets wees. De
 * codewijziging voorkomt nieuwe gevallen, maar raakt de bestaande niet — dit
 * script wel.
 *
 * Het corrigeert alleen onmogelijke standen; het verzint geen leesgeschiedenis:
 *
 *   - teller hoger dan het totaal aantal berichten in het gesprek  -> afgetopt
 *   - gesprek zonder enig bericht                                  -> leeggemaakt
 *   - teller voor iemand die geen deelnemer (meer) is              -> verwijderd
 *   - teller op nul of negatief                                    -> verwijderd
 *
 * Gebruik:
 *   set GOOGLE_APPLICATION_CREDENTIALS=C:\pad\naar\service-account.json
 *   node reconcile-unread.js            # laat alleen zien wat er zou wijzigen
 *   node reconcile-unread.js --apply    # voert de correcties uit
 */

const admin = require('firebase-admin');

const PROJECT_ID = 'voetbalplanner-b4062';
const uitvoeren = process.argv.includes('--apply');

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

/**
 * Hoeveel berichten staan er in dit gesprek?
 *
 * Drie collecties, want de app is met de jaren uit elkaar gegroeid: de
 * universele chat schrijft naar chatMessages, de teamchat naar teamChats en de
 * groepschat naar groupMessages.
 */
async function aantalBerichten(convId) {
  try {
    if (convId.startsWith('team_')) {
      const teamId = convId.slice('team_'.length);
      const q = await db.collection('teamChats').where('teamId', '==', teamId).count().get();
      return q.data().count;
    }
    if (convId.startsWith('group_')) {
      const groupId = convId.slice('group_'.length);
      const q = await db.collection('groupMessages').where('groupId', '==', groupId).count().get();
      return q.data().count;
    }
    const q = await db
      .collection('chatMessages')
      .where('conversationId', '==', convId)
      .count()
      .get();
    return q.data().count;
  } catch (e) {
    console.error('tellen mislukt voor ' + convId + ': ' + e.message);
    return null;
  }
}

/** Wie hoort er bij dit gesprek? Voor groepen telt de ledenlijst van de groep. */
async function deelnemers(convId, data) {
  const uitConv = Array.isArray(data.participantIds) ? data.participantIds : [];
  if (!convId.startsWith('group_')) return new Set(uitConv);

  const groupId = convId.slice('group_'.length);
  try {
    let snap = await db.collection('chatGroups').doc(groupId).get();
    let g = snap.exists ? snap.data() : null;
    if (!g) {
      const q = await db.collection('chatGroups').where('name', '==', groupId).limit(1).get();
      if (!q.empty) g = q.docs[0].data();
    }
    if (g && Array.isArray(g.members)) {
      // Alleen wie nu nog lid is. participantIds groeit alleen maar aan.
      return new Set(g.members);
    }
  } catch (_) {}
  return new Set(uitConv);
}

async function main() {
  console.log('Project : ' + PROJECT_ID);
  console.log('Modus   : ' + (uitvoeren ? 'CORRIGEREN' : 'alleen tonen (gebruik --apply)'));
  console.log('');

  const snap = await db.collection('chatConversations').get();

  let bekeken = 0;
  let gewijzigd = 0;
  let verwijderdeTellers = 0;
  let afgetopteTellers = 0;

  for (const doc of snap.docs) {
    const data = doc.data() || {};
    const rauw = data.unreadByUser;
    if (!rauw || typeof rauw !== 'object') continue;

    const adressen = Object.keys(rauw);
    if (adressen.length === 0) continue;

    bekeken++;

    const berichten = await aantalBerichten(doc.id);
    if (berichten === null) continue;
    const hoort = await deelnemers(doc.id, data);

    const nieuw = {};
    const redenen = [];

    for (const adres of adressen) {
      const v = typeof rauw[adres] === 'number' ? rauw[adres] : 0;

      if (v <= 0) {
        continue; // nul hoeft niet bewaard te worden
      }
      if (berichten === 0) {
        redenen.push(adres + ': ' + v + ' -> weg (gesprek heeft geen berichten)');
        verwijderdeTellers++;
        continue;
      }
      if (hoort.size > 0 && !hoort.has(adres)) {
        redenen.push(adres + ': ' + v + ' -> weg (geen deelnemer meer)');
        verwijderdeTellers++;
        continue;
      }
      if (v > berichten) {
        redenen.push(adres + ': ' + v + ' -> ' + berichten + ' (meer dan er berichten zijn)');
        nieuw[adres] = berichten;
        afgetopteTellers++;
        continue;
      }
      nieuw[adres] = v;
    }

    // Niets veranderd? Dan ook niet schrijven.
    const zelfde =
      Object.keys(nieuw).length === adressen.filter((a) => (rauw[a] || 0) > 0).length &&
      Object.keys(nieuw).every((a) => nieuw[a] === rauw[a]);
    if (zelfde && redenen.length === 0) continue;

    gewijzigd++;
    console.log('─'.repeat(70));
    console.log(doc.id + '   (' + berichten + ' berichten, type=' + (data.type || '—') + ')');
    for (const r of redenen) console.log('   ' + r);
    if (redenen.length === 0) console.log('   nul-tellers opgeruimd');

    if (uitvoeren) {
      // update() en geen set+merge: de hele map moet vervangen worden, want
      // met merge blijven verwijderde adressen gewoon staan. Alleen dit ene
      // veld wordt aangeraakt; de rest van het document blijft.
      try {
        await doc.ref.update({ unreadByUser: nieuw });
      } catch (e) {
        console.error('   schrijven mislukt: ' + e.message);
      }
    }
  }

  console.log('─'.repeat(70));
  console.log('Gesprekken met tellers : ' + bekeken);
  console.log('Aangepast              : ' + gewijzigd);
  console.log('Tellers verwijderd     : ' + verwijderdeTellers);
  console.log('Tellers afgetopt       : ' + afgetopteTellers);
  if (!uitvoeren && gewijzigd > 0) {
    console.log('');
    console.log('Er is niets gewijzigd. Draai opnieuw met --apply om dit door te voeren.');
  }
}

main().then(
  () => process.exit(0),
  (e) => {
    console.error('Mislukt: ' + (e && e.message ? e.message : e));
    process.exit(1);
  },
);
