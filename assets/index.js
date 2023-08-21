var stopRequested = false;

$(document).ready(function() {
    var counters = { live: 0, dead: 0, unknown: 0 };

    function updateCounter(type, count) {
        $(`.panel-title.${type} .badge`).text(counters[type] += count);
    }

    $("#ccData").on("input", function() {
        var allLinesValid = $(this).val().trim().split("\n")
            .every(line => /^(\d{16})\|(\d{2})\|(\d{4})\|(\d{3})$/.test(line));
        $("#submitBtn").prop("disabled", !allLinesValid);
    });

    $("#form").submit(function(event) {
        event.preventDefault();
        var ccLines = $("#ccData").val().trim().split("\n");
        var currentIndex = 0;
        $("#stopBtn").prop("disabled", false);

        function sendRequest() {
            if (currentIndex < ccLines.length && !stopRequested) {
                var [ccn, month, year, cvc] = ccLines[currentIndex].split("|");

                $.ajax({
                    url: "./api.php", type: "POST",
                    data: { ccn, month, year, cvc },
                    success: function(response) {
                        var type = response[0]?.status.toLowerCase();
                        if (type) {
                            updateCounter(type, 1);
                            var panelClass = type === "live" ? "success" : type === "dead" ? "danger" : "warning";
                            $(`.panel-body.${panelClass}`).append(
                                `<div>[${response[0].message}] ${ccLines[currentIndex]}  ~ ${response[1].bank} | ${response[1].country} | ${response[1].type} | ${response[1].brand}</div>`
                            );
                        }
                        currentIndex++;
                        setTimeout(sendRequest, 3000);
                    },
                    error: function(status, error) {
                        console.error(status, error);
                        currentIndex++;
                        setTimeout(sendRequest, 3000);
                    }
                });
            }
        }
        sendRequest();
    });

    $("#stopBtn").click(function() {
        stopRequested = true;
    });

    $("#submitBtn").click(function() {
        if (stopRequested) {
            stopRequested = false;
            sendRequest();
        }
    });
});
