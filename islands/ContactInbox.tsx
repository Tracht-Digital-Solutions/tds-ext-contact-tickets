import { useEffect, useState } from "react";

interface Message {
  id: number;
  name: string;
  email: string;
  company: string | null;
  subject: string | null;
  status: "new" | "handled" | "spam";
  created_at: string;
}

const api = (path: string, init?: RequestInit) => fetch(path, { credentials: "include", ...init });

/**
 * Contact-form inbox (checkpoint-1): list submissions, filter by status, and set
 * a message's status (new → handled / spam). The full detail view + email reply
 * lands in the next frontend checkpoint.
 */
export default function ContactInbox() {
  const [messages, setMessages] = useState<Message[] | null>(null);
  const [filter, setFilter] = useState<string>("new");

  const load = (status: string) =>
    api(`/contact/messages${status ? `?status=${status}` : ""}`)
      .then((r) => (r.ok ? r.json() : { messages: [] }))
      .then((d) => setMessages(d.messages ?? []))
      .catch(() => setMessages([]));

  useEffect(() => {
    load(filter);
  }, [filter]);

  const setStatus = async (m: Message, status: string) => {
    await api(`/contact/messages/${m.id}`, {
      method: "PATCH",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ status }),
    });
    load(filter);
  };

  return (
    <div className="contact-inbox">
      <div className="contact-inbox__filter">
        {["new", "handled", "spam", ""].map((s) => (
          <button
            key={s || "all"}
            type="button"
            className={`chip chip--${filter === s ? "info" : "neutral"}`}
            onClick={() => setFilter(s)}
          >
            {s === "" ? "Alle" : s === "new" ? "Neu" : s === "handled" ? "Erledigt" : "Spam"}
          </button>
        ))}
      </div>

      {messages === null ? (
        <p>Wird geladen …</p>
      ) : messages.length === 0 ? (
        <p>Keine Anfragen.</p>
      ) : (
        <ul className="contact-inbox__list">
          {messages.map((m) => (
            <li key={m.id} className="contact-inbox__row">
              <span className="contact-inbox__from">
                <strong>{m.name}</strong> &lt;{m.email}&gt;
                {m.company ? <em> · {m.company}</em> : null}
              </span>
              {m.subject ? <span className="contact-inbox__subject">{m.subject}</span> : null}
              <span className="contact-inbox__actions">
                {m.status !== "handled" ? (
                  <button type="button" onClick={() => setStatus(m, "handled")}>Erledigt</button>
                ) : null}
                {m.status !== "spam" ? (
                  <button type="button" onClick={() => setStatus(m, "spam")}>Spam</button>
                ) : null}
              </span>
            </li>
          ))}
        </ul>
      )}
    </div>
  );
}
