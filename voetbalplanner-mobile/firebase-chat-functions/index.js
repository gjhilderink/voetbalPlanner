// Chat push-notificaties voor de voetbalplanner-app.
//
// Standalone Firebase Functions codebase (los van de FlutterFlow-gegenereerde
// functions onder generated_code/firebase/ — die worden bij elke FF-export
// overschreven). Deze functie reageert op nieuwe `chatMessages`-documenten en
// stuurt een FCM push naar de juiste topics:
//
//   - team-conversatie   -> topic `team_<teamId>`        (iedereen in het team
//                                                          is hierop geabonneerd)
//   - direct / staffgroep -> topic `user_<sanitize(email)>` per deelnemer,
//                            BEHALVE de afzender (die krijgt geen self-push)
//
// De client abonneert op `user_<sanitize(eigen-email)>` + `team_<teamId>` via de
// custom action `subscribeToChatTopics`. De sanitisatie hieronder MOET exact
// gelijk zijn aan die in de Dart-client.

const { onDocumentCreated } = require("firebase-functions/v2/firestore");
const { setGlobalOptions } = require("firebase-functions/v2");
const { initializeApp } = require("firebase-admin/app");
const { getFirestore, FieldValue, FieldPath } = require("firebase-admin/firestore");
const { getMessaging } = require("firebase-admin/messaging");

initializeApp();
setGlobalOptions({ region: "us-central1", maxInstances: 10 });

const db = getFirestore();

// Houd dit identiek aan sanitize() in de Dart custom action subscribeToChatTopics.
// FCM-topicnamen mogen alleen [a-zA-Z0-9-_.~%] bevatten; '@' is ongeldig.
function sanitize(email) {
  return (email || "").toLowerCase().replace(/[^a-z0-9]/g, "_");
}

/**
 * Hoogt de ongelezen-teller op voor iedereen behalve de afzender.
 *
 * Dit deed voorheen het toestel van de afzender. Dat is niet te vertrouwen: de
 * telefoon bepaalde zelf wie de deelnemers waren, en als de schrijfactie
 * halverwege strandde bleef het verschil staan. Zo ontstonden tellingen die
 * naar gesprekken wezen waar niets nieuws stond.
 *
 * Hier gebeurt het één keer, op dezelfde plek als de push en met dezelfde
 * ontvangerslijst — wat een melding krijgt, krijgt ook een teller.
 */
async function hoogOngelezenOp(conversationId, ontvangers, senderId, laatsteTekst) {
  const doelen = (ontvangers || []).filter((e) => e && e !== senderId);
  const ref = db.collection("chatConversations").doc(conversationId);

  const meta = { lastMessageAt: FieldValue.serverTimestamp() };
  if (laatsteTekst) meta.lastMessage = laatsteTekst;

  try {
    await ref.set(meta, { merge: true });
  } catch (e) {
    console.error(`metadata op ${conversationId} bijwerken mislukt:`, e);
  }

  if (doelen.length === 0) return;

  // FieldPath per ontvanger: increment werkt niet binnen een geneste map bij
  // set+merge, daarom een aparte update().
  const updates = {};
  for (const email of doelen) {
    updates[new FieldPath("unreadByUser", email)] = FieldValue.increment(1);
  }

  try {
    await ref.update(updates);
  } catch (e) {
    // Document bestaat wel maar unreadByUser ontbreekt nog.
    const init = {};
    for (const email of doelen) init[email] = 1;
    try {
      await ref.set({ unreadByUser: init }, { merge: true });
    } catch (e2) {
      console.error(`teller op ${conversationId} bijwerken mislukt:`, e2);
    }
  }
}

exports.notifyOnChatMessage = onDocumentCreated(
  "chatMessages/{messageId}",
  async (event) => {
    const snap = event.data;
    if (!snap) return;

    const msg = snap.data() || {};
    const conversationId = (msg.conversationId || "").toString();
    const senderId = (msg.senderId || "").toString(); // e-mail van de afzender
    const senderName = (msg.senderName || "Nieuw bericht").toString();
    const text = (msg.text || "").toString();
    if (!conversationId || !text) return;

    // Conversatie-metadata ophalen (type / teamId / titel / deelnemers).
    let conv = {};
    try {
      const convSnap = await db
        .collection("chatConversations")
        .doc(conversationId)
        .get();
      conv = convSnap.exists ? convSnap.data() || {} : {};
    } catch (e) {
      console.error(`kon conversation ${conversationId} niet lezen:`, e);
    }

    const type = (conv.type || "").toString();
    const teamId = (conv.teamId || "").toString();
    const title = (conv.title || senderName).toString();
    const participantIds = Array.isArray(conv.participantIds)
      ? conv.participantIds
      : [];

    // Bepaal doel-topics.
    const topics = [];
    if (type === "team" && teamId) {
      topics.push(`team_${teamId}`);
    } else {
      for (const email of participantIds) {
        if (email && email !== senderId) {
          topics.push(`user_${sanitize(email)}`);
        }
      }
    }
    if (topics.length === 0) {
      console.log(`geen ontvangers voor ${conversationId} (type=${type})`);
      return;
    }

    const notification = {
      title: title,
      body: type === "direct" ? text : `${senderName}: ${text}`,
    };
    // Alle data-waarden moeten strings zijn (FCM-eis). parameterData volgt het
    // formaat dat push_notifications_handler.dart verwacht voor deep-linking
    // naar ChatDetailPage (params conversationId + title als plain strings).
    const data = {
      initialPageName: "ChatDetailPage",
      parameterData: JSON.stringify({ conversationId: conversationId, title: title }),
      conversationId: conversationId,
      senderId: senderId,
    };

    const results = await Promise.all(
      topics.map((topic) =>
        getMessaging()
          .send({ topic, notification, data })
          .then(() => true)
          .catch((e) => {
            console.error(`push naar topic ${topic} mislukt:`, e);
            return false;
          })
      )
    );

    const sent = results.filter(Boolean).length;
    console.log(
      `chatMessage ${event.params.messageId}: ${sent}/${topics.length} topics gepusht (type=${type})`
    );

    await hoogOngelezenOp(conversationId, participantIds, senderId, text);
  }
);

// Teamchat loopt via de oude TeamChatPage, die naar de `teamChats`-collectie
// schrijft (niet `chatMessages`). Aparte trigger zodat ook teamchat-berichten
// een push naar het teamtopic sturen.
exports.notifyOnTeamChat = onDocumentCreated(
  "teamChats/{messageId}",
  async (event) => {
    const snap = event.data;
    if (!snap) return;

    const msg = snap.data() || {};
    const teamId = (msg.teamId || "").toString();
    const senderId = (msg.senderId || "").toString();
    const senderName = (msg.senderName || "Teamchat").toString();
    const text = (msg.text || "").toString();
    if (!teamId || !text) return;

    const notification = {
      title: "Teamchat",
      body: `${senderName}: ${text}`,
    };
    const data = {
      initialPageName: "TeamChatPage",
      parameterData: JSON.stringify({ teamId: teamId, teamName: "" }),
      teamId: teamId,
      senderId: senderId,
    };

    try {
      await getMessaging().send({ topic: `team_${teamId}`, notification, data });
      console.log(`teamChat ${event.params.messageId}: push naar team_${teamId}`);
    } catch (e) {
      console.error(`teamChat push naar team_${teamId} mislukt:`, e);
    }

    // De teller staat op de bijbehorende conversatie `team_<teamId>`, ook al
    // staat het bericht zelf in een andere collectie.
    const convId = `team_${teamId}`;
    let deelnemers = [];
    try {
      const convSnap = await db.collection("chatConversations").doc(convId).get();
      const conv = convSnap.exists ? convSnap.data() || {} : {};
      if (Array.isArray(conv.participantIds)) deelnemers = conv.participantIds;
    } catch (e) {
      console.error(`kon ${convId} niet lezen:`, e);
    }
    await hoogOngelezenOp(convId, deelnemers, senderId, text);
  }
);

// Groepschats schrijven naar `groupMessages` (de oude GroupChatPage) en werden
// door geen enkele trigger opgepikt: geen melding, geen teller. Dat was het
// grootste gat in de meldingen — een groepsbericht kwam alleen aan als iemand
// toevallig de app openhad staan.
//
// De groep wordt op doc-id én op naam opgezocht: de app geeft de groepsnaam
// door als groupId.
exports.notifyOnGroupMessage = onDocumentCreated(
  "groupMessages/{messageId}",
  async (event) => {
    const snap = event.data;
    if (!snap) return;

    const msg = snap.data() || {};
    const groupId = (msg.groupId || "").toString();
    const senderId = (msg.senderId || "").toString();
    const senderName = (msg.senderName || "Groepschat").toString();
    const text = (msg.text || "").toString();
    if (!groupId || !text) return;

    let groep = null;
    try {
      const opId = await db.collection("chatGroups").doc(groupId).get();
      if (opId.exists) {
        groep = opId.data() || {};
      } else {
        const opNaam = await db
          .collection("chatGroups")
          .where("name", "==", groupId)
          .limit(1)
          .get();
        if (!opNaam.empty) groep = opNaam.docs[0].data() || {};
      }
    } catch (e) {
      console.error(`kon groep ${groupId} niet lezen:`, e);
    }

    const leden = groep && Array.isArray(groep.members) ? groep.members : [];
    const naam = (groep && groep.name ? groep.name : groupId).toString();

    const notification = {
      title: naam,
      body: `${senderName}: ${text}`,
    };
    const data = {
      initialPageName: "GroupChatPage",
      parameterData: JSON.stringify({ groupId: groupId, groupName: naam }),
      groupId: groupId,
      senderId: senderId,
    };

    const topics = leden
      .filter((e) => e && e !== senderId)
      .map((e) => `user_${sanitize(e)}`);

    const results = await Promise.all(
      topics.map((topic) =>
        getMessaging()
          .send({ topic, notification, data })
          .then(() => true)
          .catch((e) => {
            console.error(`push naar topic ${topic} mislukt:`, e);
            return false;
          })
      )
    );
    console.log(
      `groupMessage ${event.params.messageId}: ${results.filter(Boolean).length}/${topics.length} gepusht`
    );

    await hoogOngelezenOp(`group_${groupId}`, leden, senderId, text);
  }
);
