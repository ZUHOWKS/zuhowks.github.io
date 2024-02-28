/*
 *   Welcome Title
 */ let welcomeTitle = document.querySelector("#welcome-title");
let holoSphere = document.querySelector("#holo-sphere");
let presentationSection = document.querySelector("#presentation");
let projectsSection = document.querySelector("#projects");
scrollTo({
    top: window.scrollY,
    left: 0,
    behavior: "smooth"
});
setTimeout(()=>{
    document.querySelector("#welcome-title-line-1").style.animationPlayState = "running";
    document.querySelector("#welcome-title-line-2").style.animationPlayState = "running";
    document.querySelector("#welcome-title-line-3").style.animationPlayState = "running";
    document.querySelector("#welcome-title-line-4").style.animationPlayState = "running";
}, 300);
setTimeout(()=>{
    document.querySelector("#welcome-title-line-1").classList.remove("lineUp");
    document.querySelector("#welcome-title-line-1").classList.add("lineUpOut");
}, 1700);
let clientX = 0;
let clientY = 0;
addEventListener("mousemove", (e)=>{
    clientX = e.clientX;
    clientY = e.clientY;
    if (window.scrollY < 300) updatePerspectiveMousePosition(welcomeTitle, 0, 12, 2);
    if (1100 <= window.scrollY && window.scrollY <= 1600) updatePerspectiveMousePosition(document.querySelector("#presentation-text"), 0, 0, 2);
});
function updatePerspectiveMousePosition(element, alphaLeft, alphaTop, opacityStrength) {
    const widthCenter = window.innerWidth / 2;
    const heightCenter = window.innerHeight / 2;
    const dist = Math.min(Math.sqrt(Math.pow(clientX - widthCenter, 2) + Math.pow(clientY - heightCenter, 2)), Math.sqrt(Math.pow(widthCenter, 2) + Math.pow(heightCenter, 2)));
    const speed = 0.003;
    element.style.left = alphaLeft + dist * speed * Math.cos(Math.acos((widthCenter - clientX) / dist)) + "rem";
    element.style.top = alphaTop + dist * speed * 1.5 * Math.sin(Math.asin((heightCenter - clientY) / dist)) + "rem";
    element.style.opacity = Math.min(1, heightCenter * 2 / (opacityStrength * dist));
}
addEventListener("scroll", ()=>{
    outOfHoloSphereScroll();
    enterSimulationScroll();
    myUniverseScroll();
});
welcomeTitle.addEventListener("click", ()=>{
    scrollTo({
        top: 300,
        left: 0,
        behavior: "smooth"
    });
    setTimeout(()=>scrollTo({
            top: 500,
            left: 0,
            behavior: "smooth"
        }), 200);
    setTimeout(()=>scrollTo({
            top: 750,
            left: 0,
            behavior: "smooth"
        }), 350);
    setTimeout(()=>scrollTo({
            top: 1200,
            left: 0,
            behavior: "smooth"
        }), 400);
});
function outOfHoloSphereScroll() {
    const scrollBegin = 350;
    const scrollEnding = 500;
    let speed = 0.001;
    if (scrollBegin <= window.scrollY <= scrollEnding) {
        speed = 0.01;
        const scrollHiddenTitle = 410;
        welcomeTitle.style.opacity = Math.max(0, 1 - window.scrollY / scrollHiddenTitle);
    } else if (window.scrollY < scrollBegin) updatePerspectiveMousePosition();
    if (scrollEnding < window.scrollY) welcomeTitle.style.visibility = "hidden";
    else welcomeTitle.style.visibility = "visible";
    holoSphere.style.top = 100 - Math.min(100, window.scrollY) + "px";
    welcomeTitle.style.transform = "perspective(0px) translateZ(" + -Math.max(0, window.scrollY - scrollBegin) * speed + "px";
}
/*
 * Presentation Section
 */ function enterSimulationScroll() {
    const scrollBegin = 800;
    const scrollEnding = 2000;
    if (scrollBegin - 300 < window.scrollY) {
        document.querySelectorAll("#presentation-text .lineLeft").forEach((element)=>element.style.animationPlayState = "running");
        document.querySelectorAll("#presentation-text .lineUp").forEach((element)=>element.style.animationPlayState = "running");
    }
    if (scrollBegin <= window.scrollY && window.scrollY <= scrollEnding) {
        presentationSection.style.visibility = "visible";
        if (window.scrollY <= scrollEnding * 0.75) presentationSection.style.opacity = Math.min(1, (window.scrollY - 800) / 300);
        else presentationSection.style.opacity = Math.max(0, (2000 - window.scrollY) / 300);
    } else presentationSection.style.visibility = "hidden";
}
document.querySelector("#enter-universe").addEventListener("click", ()=>{
    scrollTo({
        top: 1800,
        left: 0,
        behavior: "smooth"
    });
    setTimeout(()=>scrollTo({
            top: 2100,
            left: 0,
            behavior: "smooth"
        }), 175);
    setTimeout(()=>scrollTo({
            top: 2300,
            left: 0,
            behavior: "smooth"
        }), 285);
    setTimeout(()=>scrollTo({
            top: 2600,
            left: 0,
            behavior: "smooth"
        }), 375);
});
let projectsAnimation = false;
let asiluxVideoStarted = false;
let confirmBlocklingArrived = false;
function changeProjectTheme(background, textColor) {
    projectsSection.style.background = background;
    document.querySelectorAll("#projects h1").forEach((e)=>e.style.color = textColor);
    document.querySelectorAll("#projects h2").forEach((e)=>e.style.color = textColor);
    document.querySelectorAll("#projects h3").forEach((e)=>e.style.color = textColor);
    document.querySelectorAll("#projects p").forEach((e)=>e.style.color = textColor);
    document.querySelectorAll("#projects a").forEach((e)=>e.style.color = textColor);
}
function myUniverseScroll() {
    const scrollBegin = 2325;
    const scrollEnding = 14000;
    projectsSection.style.opacity = Math.min(1, (window.scrollY - scrollBegin) / 200);
    projectsSection.style.transform = "translateX(" + Math.max(-5, -5 + 5 * (scrollBegin + 200 - window.scrollY) / 200) + "em)";
    if (scrollBegin <= window.scrollY && window.scrollY <= scrollEnding) {
        projectsSection.style.visibility = "visible";
        /* SCROLL CONTROLLER */ // Appear Project section
        if (projectsAnimation && window.scrollY <= scrollBegin + 200) {
            projectsSection.style.background = "none";
            document.querySelectorAll("#scc h1").forEach((e)=>e.style.color = "black !important");
        } else if (scrollBegin + 200 < window.scrollY && window.scrollY <= scrollBegin + 2000) {
            let sectionPos = -37 - 76.5 * (window.scrollY - scrollBegin) / 2000;
            document.querySelector("#scc").style.transform = "translateX(" + sectionPos + "vw)";
            let titlePos = 8 - (window.scrollY - scrollBegin - 200) / 2000 * 22.5;
            document.querySelector("#scc #scc-title").style.transform = "translateX(" + titlePos + "em)";
        } else if (scrollBegin + 2000 < window.scrollY && window.scrollY <= scrollBegin + 4200) {
            let sectionPos = 220 * (window.scrollY - scrollBegin - 2000) / 2200;
            document.querySelector("#scc-video video").play();
            document.querySelector("#scc").style.transform = "translateX(-113.5vw) translateY(-" + sectionPos + "vh)";
            document.querySelector("#scc #scc-title").style.transform = "translateX(-12.5em)";
            document.querySelector("#asilux").style.transform = "translateY(-" + (sectionPos + 25) + "vh)";
        } else if (scrollBegin + 4100 < window.scrollY && window.scrollY <= scrollBegin + 5600) {
            let sectionPos = 65 * (window.scrollY - scrollBegin - 4100) / 1500;
            document.querySelector("#scc").style.transform = "translateX(-113.5vw) translateY(-" + (sectionPos * 2 + 220) + "vh)";
            document.querySelector("#asilux").style.transform = "translateX(5em) translateY(-" + (sectionPos + 175) + "vh)";
        } else if (scrollBegin + 5600 < window.scrollY && window.scrollY <= scrollBegin + 6500) {
            let sectionPos = 40 * (window.scrollY - scrollBegin - 5600) / 900;
            document.querySelector("#scc").style.transform = "translateX(-113.5vw) translateY(-365vh)";
            document.querySelector("#asilux").style.transform = "translateX(5em) translateY(-" + (sectionPos + 240) + "vh)";
        } else if (scrollBegin + 6500 < window.scrollY && window.scrollY <= scrollEnding) {
            let sectionPos = 320 * (window.scrollY - scrollBegin - 6500) / 4000;
            document.querySelector("#scc").style.transform = "translateX(-113.5vw) translateY(-365vh)";
            document.querySelector("#asilux").style.transform = "translateX(5em) translateY(-" + (sectionPos + 280) + "vh)";
        }
        /* RUN ANIMATION */ //SCC
        if (scrollBegin <= window.scrollY && window.scrollY <= scrollBegin + 3800) document.querySelector("#scc-video video").play();
        else document.querySelector("#scc-video video").pause();
        if (scrollBegin + 25 <= window.scrollY) {
            document.querySelector("#projects-container").style.animationPlayState = "running";
            projectsAnimation = true;
            document.querySelectorAll("#scc .lineLeft").forEach((element)=>element.style.animationPlayState = "running");
            document.querySelectorAll("#scc .lineUp").forEach((element)=>element.style.animationPlayState = "running");
        }
        // Asilux
        if (scrollBegin + 4600 <= window.scrollY) document.querySelector("#asilux").style.opacity = 1;
        else document.querySelector("#asilux").style.opacity = 0;
        // Blockling Video
        if (scrollBegin + 4500 <= window.scrollY && window.scrollY <= scrollBegin + 6600) {
            document.querySelectorAll("#asilux .lineLeft").forEach((element)=>element.style.animationPlayState = "running");
            document.querySelectorAll("#asilux .lineUp").forEach((element)=>element.style.animationPlayState = "running");
            if (!asiluxVideoStarted) {
                asiluxVideoStarted = true;
                document.querySelector("#asilux-blockling-2").pause();
                document.querySelector("#asilux-blockling-1").play();
                setTimeout(()=>{
                    document.querySelector("#asilux-blockling-2").style.opacity = 1;
                    document.querySelector("#asilux-blockling-2").play();
                    document.querySelector("#asilux-blockling-1").pause();
                    document.querySelector("#asilux-blockling-1").remove();
                    confirmBlocklingArrived = true;
                }, 3525);
            } else if (confirmBlocklingArrived) setTimeout(()=>{
                document.querySelector("#asilux-blockling-2").play();
            }, 200);
        } else if (asiluxVideoStarted) document.querySelector("#asilux-blockling-2").pause();
        // Demana video
        if (scrollBegin + 6750 <= window.scrollY && window.scrollY <= scrollBegin + 8550) document.querySelector("#asilux-demana-1").play();
        else document.querySelector("#asilux-demana-1").pause();
        /* PROJECT TRANSITIONS */ if (scrollBegin + 150 < window.scrollY && window.scrollY <= scrollBegin + 4000) {
            let background = "#d5aa81";
            let white = "#fff";
            projectsSection.style.background = background;
            changeProjectTheme(background, white);
            document.querySelector("#scc .title").style.mixBlendMode = "difference";
        } else if (scrollBegin + 4900 < window.scrollY && window.scrollY <= scrollEnding) {
            let background = "#859b50";
            let white = "#fdfdfd";
            changeProjectTheme(background, white);
        } else {
            changeProjectTheme("none", "black");
            document.querySelector("#scc .title").style.mixBlendMode = "normal";
        }
    } else projectsSection.style.visibility = "hidden";
}

//# sourceMappingURL=index.81cf15bf.js.map
