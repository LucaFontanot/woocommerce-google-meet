jQuery(async function($){
    const id = "wgm_event_id";
    async function fetchAvailableEvents(year, month) {
        try{
            let url = new URL(WGM_AVAIL.endpoint)
            url.searchParams.append('year', year);
            url.searchParams.append('month', month + 1);
            let response = await fetch(url.toString());
            let data = await response.json();
            return data;
        }catch (error){
            console.error("Error fetching events:", error);
            return {};
        }
    }

    function generateDailyEventsBox(day, events) {
        let container = document.createElement("div");
        container.classList.add("wgm_event_box");
        let title = document.createElement("h3");
        title.classList.add("wgm_event_title");
        title.innerText = day;
        container.appendChild(title);
        events.forEach(event => {
            let eventDiv = document.createElement("input");
            eventDiv.type = "radio";
            eventDiv.name = id;
            eventDiv.value = event.id;
            eventDiv.id = `event_${event.id}`;
            eventDiv.required = true;
            let startDate = new Date(event.start);
            let endDate = new Date(event.end);
            let label = document.createElement("label");
            label.htmlFor = eventDiv.id;
            label.innerText = `${startDate.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'})} - ${endDate.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'})}`;
            container.appendChild(eventDiv);
            container.appendChild(label);
            container.appendChild(document.createElement("br"));
        });
        return container;
    }
    let data = $('#wgm-picker');
    let container = document.createElement("div");
    container.classList.add("wgm_events");

    let year = new Date().getFullYear();
    let month = new Date().getMonth();

    let yearPicker = document.createElement("select");
    for (let y = year; y <= year + 5; y++){
        let option = document.createElement("option");
        option.value = y;
        option.innerText = y;
        if (y === year) option.selected = true;
        yearPicker.appendChild(option);
    }
    yearPicker.id = "wgm_event_year";
    yearPicker.addEventListener("change", async function(){
        year = parseInt(this.value);
        await updateEvents(year, month);
    });
    data.append(yearPicker);

    let monthPicker = document.createElement("select");
    monthPicker.id = "wgm_event_month";
    for (let m = 0; m < 12; m++){
        let option = document.createElement("option");
        option.value = m;
        option.innerText = new Date(0, m).toLocaleString('default', { month: 'long' });
        if (m === month) option.selected = true;
        monthPicker.appendChild(option);
    }
    monthPicker.addEventListener("change", async function(){
        month = parseInt(this.value);
        await updateEvents(year, month);
    });
    data.append(monthPicker);


    data.append(container);
    async function updateEvents(year, month){
        container.innerHTML = '';
        let events = await fetchAvailableEvents(year, month);
        if (events.events){
            for (let day in events.events){
                let dailyBox = generateDailyEventsBox(day, events.events[day]);
                container.appendChild(dailyBox);
            }
        }
    }

    await updateEvents(year, month);


});