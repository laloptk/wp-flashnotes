(function () {
    function init_card(el) {
        const send_btn = el.querySelector(".wpfn-send-answer");
        const feedback = el.querySelector(".wpfn-feedback");
        const radios = el.querySelectorAll('input[type="radio"]');

        if (!send_btn || radios.length === 0) return;

        let user_answer = null;

        radios.forEach((radio) => {
            radio.addEventListener("change", (e) => {
                user_answer = e.target.value;
                send_btn.hidden = false;
                if (feedback) feedback.hidden = true;
            });
        });

        send_btn.addEventListener("click", async () => {
            if (user_answer === null) return;

            // Example: compare locally if you embed correct answer as data attr
            // const correct = el.dataset.correct; // "true"|"false"
            // const ok = user_answer === correct;

            // Or: POST to REST API to validate
            try {
                const block_id = el.dataset.id;

                // Replace with your real endpoint + nonce strategy
                const res = await fetch("/wp-json/wpfn/v1/answer", {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json",
                        // "X-WP-Nonce": wpApiSettings.nonce,
                    },
                    body: JSON.stringify({ block_id, answer: user_answer }),
                });

                const data = await res.json();

                if (feedback) {
                    feedback.textContent = data?.message || (data?.correct ? "Correct" : "Incorrect");
                    feedback.hidden = false;
                }
            } catch (e) {
                if (feedback) {
                    feedback.textContent = "Error sending answer.";
                    feedback.hidden = false;
                }
            }
        });
    }

    document.addEventListener("DOMContentLoaded", () => {
        document.querySelectorAll(".wpfn-card").forEach(init_card);
    });
})();
