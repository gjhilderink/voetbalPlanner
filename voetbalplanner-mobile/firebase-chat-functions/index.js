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
const { getFirestore } = require("firebase-admin/firestore");
const { getMessaging } = require("firebase-admin/messaging");

initializeApp();
setGlobalOptions({ region: "us-central1", maxInstances: 10 });

const db = getFirestore();

// Houd dit identiek aan sanitize() in de Dart custom action subscribeToChatTopics.
// FCM-topicnamen mogen alleen [a-zA-Z0-9-_.~%] bevatten; '@' is ongeldig.
function sanitize(email) {
  return (email || "").toLowerCase().replace(/[^a-z0-9]/g, "_");
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
  }
);
