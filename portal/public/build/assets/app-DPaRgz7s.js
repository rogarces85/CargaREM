const _="rem-theme",N=["light","dark","system"],E=document.documentElement,j=window.matchMedia("(prefers-color-scheme: dark)");function b(){const t=localStorage.getItem(_);return N.includes(t)?t:"system"}function k(t){return t==="light"||t==="dark"?t:j.matches?"dark":"light"}function $(t){document.querySelectorAll("[data-theme-set]").forEach(e=>{e.setAttribute("aria-pressed",String(e.dataset.themeSet===t))}),document.querySelectorAll("[data-theme-toggle]").forEach(e=>{const a=k(t)==="dark";e.setAttribute("aria-label",a?"Cambiar a tema claro":"Cambiar a tema oscuro"),e.dataset.themeState=a?"dark":"light"})}function S(t){const e=k(t);return E.classList.toggle("dark",e==="dark"),E.classList.toggle("light",e==="light"),E.dataset.themePreference=t,$(t),document.dispatchEvent(new CustomEvent("rem:themechange",{detail:{preference:t,effective:e}})),e}function q(t){N.includes(t)&&(localStorage.setItem(_,t),S(t))}function F(){S(b()),document.querySelectorAll("[data-theme-set]").forEach(t=>{t.addEventListener("click",()=>q(t.dataset.themeSet))}),document.querySelectorAll("[data-theme-toggle]").forEach(t=>{t.addEventListener("click",()=>{q(k(b())==="dark"?"light":"dark")})}),j.addEventListener("change",()=>{b()==="system"&&S("system")})}const G="rem-sidebar",v=document.documentElement;function W(){return!window.matchMedia("(min-width: 64rem)").matches}function C(t,e){document.querySelectorAll("[data-sidebar-toggle]").forEach(a=>{a.setAttribute("aria-expanded",String(e))}),t&&(t.dataset.state=e?"open":"closed")}function y(t){v.classList.remove("sidebar-open"),C(t,!1)}function X(t){var e;v.classList.add("sidebar-open"),C(t,!0),(e=t==null?void 0:t.querySelector("a, button"))==null||e.focus({preventScroll:!0})}function Y(){const t=document.querySelector("[data-sidebar]"),e=document.querySelector("[data-sidebar-overlay]");document.querySelectorAll("[data-sidebar-toggle]").forEach(a=>{a.addEventListener("click",()=>{v.classList.contains("sidebar-open")?y(t):X(t)})}),document.querySelectorAll("[data-sidebar-close]").forEach(a=>{a.addEventListener("click",()=>y(t))}),e==null||e.addEventListener("click",()=>y(t)),document.querySelectorAll("[data-sidebar-collapse]").forEach(a=>{a.addEventListener("click",()=>{const r=v.classList.toggle("sidebar-collapsed");localStorage.setItem(G,r?"collapsed":"expanded"),a.setAttribute("aria-expanded",String(!r)),a.setAttribute("aria-label",r?"Expandir la barra lateral":"Colapsar la barra lateral")})}),document.querySelectorAll("[data-nav-group-toggle]").forEach(a=>{a.addEventListener("click",()=>{const r=a.getAttribute("aria-expanded")==="true";a.setAttribute("aria-expanded",String(!r));const n=document.getElementById(a.getAttribute("aria-controls"));n&&(n.hidden=r)})}),document.addEventListener("keydown",a=>{var r;a.key==="Escape"&&v.classList.contains("sidebar-open")&&(y(t),(r=document.querySelector("[data-sidebar-toggle]"))==null||r.focus({preventScroll:!0}))}),window.matchMedia("(min-width: 64rem)").addEventListener("change",a=>{a.matches&&y(t)}),C(t,!W())}function K(){document.querySelectorAll("[data-tabs]").forEach(t=>{const e=Array.from(t.querySelectorAll("[data-tab]")),a=Array.from(t.querySelectorAll("[data-panel]"));if(e.length===0)return;const r=(c,s=!0)=>{e.forEach(o=>{const d=o===c;o.setAttribute("aria-selected",String(d)),o.tabIndex=d?0:-1}),a.forEach(o=>{o.hidden=o.dataset.panel!==c.dataset.tab}),s&&c.focus()};e.forEach(c=>{c.addEventListener("click",()=>r(c,!1)),c.addEventListener("keydown",s=>{const o=e.indexOf(c);let d=null;switch(s.key){case"ArrowRight":d=e[(o+1)%e.length];break;case"ArrowLeft":d=e[(o-1+e.length)%e.length];break;case"Home":d=e[0];break;case"End":d=e[e.length-1];break;default:return}s.preventDefault(),r(d)})});const n=e.some(c=>c.getAttribute("aria-selected")==="true");r(n?e.find(c=>c.getAttribute("aria-selected")==="true"):e[0],!1)})}const J="modulepreload",Q=function(t,e){return new URL(t,e).href},T={},B=function(e,a,r){let n=Promise.resolve();if(a&&a.length>0){let s=function(i){return Promise.all(i.map(h=>Promise.resolve(h).then(u=>({status:"fulfilled",value:u}),u=>({status:"rejected",reason:u}))))};const o=document.getElementsByTagName("link"),d=document.querySelector("meta[property=csp-nonce]"),m=(d==null?void 0:d.nonce)||(d==null?void 0:d.getAttribute("nonce"));n=s(a.map(i=>{if(i=Q(i,r),i in T)return;T[i]=!0;const h=i.endsWith(".css"),u=h?'[rel="stylesheet"]':"";if(!!r)for(let f=o.length-1;f>=0;f--){const g=o[f];if(g.href===i&&(!h||g.rel==="stylesheet"))return}else if(document.querySelector(`link[href="${i}"]${u}`))return;const l=document.createElement("link");if(l.rel=h?"stylesheet":J,h||(l.as="script"),l.crossOrigin="",l.href=i,m&&l.setAttribute("nonce",m),document.head.appendChild(l),h)return new Promise((f,g)=>{l.addEventListener("load",f),l.addEventListener("error",()=>g(new Error(`Unable to preload CSS for ${i}`)))})}))}function c(s){const o=new Event("vite:preloadError",{cancelable:!0});if(o.payload=s,window.dispatchEvent(o),!o.defaultPrevented)throw s}return n.then(s=>{for(const o of s||[])o.status==="rejected"&&c(o.reason);return e().catch(c)})};let w=null;const Z={success:{duration:4500},error:{duration:8e3,dismissible:!0},info:{duration:5e3}};async function tt(){if(w)return w;const{Notyf:t}=await B(async()=>{const{Notyf:e}=await import("./notyf.es-D99u0-HC.js");return{Notyf:e}},[],import.meta.url);return w=new t({duration:4500,ripple:!1,dismissible:!1,position:{x:"right",y:"top"},types:Object.entries(Z).map(([e,a])=>({type:e,...a}))}),w}async function x(t,e="success"){if(t)try{const a=await tt();typeof a[e]=="function"?a[e](t):a.success(t)}catch(a){console.error("[avisos] no se pudo cargar Notyf",a)}}function et(){window.remToast=x;const t=document.querySelector("[data-flashes]");t&&Array.from(t.querySelectorAll("[data-message]")).forEach((e,a)=>{window.setTimeout(()=>{x(e.dataset.message,e.dataset.type||"success")},a*250)}),document.querySelectorAll("[data-toast]").forEach(e=>{e.addEventListener("click",()=>{x(e.dataset.toast,e.dataset.toastType||"success")})})}function at(){document.querySelectorAll("[data-modal-open]").forEach(t=>{t.addEventListener("click",()=>{const e=document.getElementById(t.dataset.modalOpen);e&&typeof e.showModal=="function"&&!e.open&&e.showModal()})}),document.querySelectorAll("dialog[data-modal]").forEach(t=>{t.querySelectorAll("[data-modal-close]").forEach(e=>{e.addEventListener("click",()=>t.close())}),t.addEventListener("click",e=>{e.target===t&&t.close()})})}const rt=["var(--primary)","var(--status-ok)","var(--status-warn)","var(--status-info)","var(--status-danger)"],p={eje:"var(--muted-foreground)",borde:"var(--border)",fondo:"var(--card)"};let M=null,A=[];function ot(t){return{recibido:"Recibido",validado:"Validado",con_errores:"Con errores",en_revision:"En revisión",observado:"Observado",aprobado:"Aprobado"}[t]??String(t)}function nt(t){const e=(t.valores||[]).map(n=>Number(n)),a=(t.categorias||[]).map(ot),r={chart:{height:t.alto||260,type:t.tipo==="donut"?"donut":t.tipo==="area"?"area":"bar",fontFamily:"inherit",foreColor:p.eje,toolbar:{show:!1},animations:{enabled:!window.matchMedia("(prefers-reduced-motion: reduce)").matches}},colors:rt,dataLabels:{enabled:!1},legend:{show:!0,position:"bottom",labels:{colors:p.eje}},grid:{borderColor:p.borde,strokeDashArray:4,padding:{left:4,right:4}},tooltip:{theme:document.documentElement.classList.contains("dark")?"dark":"light"}};return t.tipo==="donut"?{...r,labels:a,series:e,stroke:{colors:[p.fondo]}}:{...r,series:[{name:t.series&&t.series[0]||"Total",data:e}],xaxis:{categories:a,labels:{style:{colors:p.eje}},axisBorder:{color:p.borde},axisTicks:{color:p.borde}},yaxis:{labels:{style:{colors:p.eje}},forceNiceScale:!0},plotOptions:{bar:{borderRadius:6,columnWidth:"55%",horizontal:!1}},stroke:{curve:"smooth",width:t.tipo==="area"?2:0}}}async function ct(){if(M)return M;const t=await B(()=>import("./apexcharts.esm-B3aHTZ1m.js"),[],import.meta.url);return M=t.default??t,M}function H(){A.forEach(t=>{try{t.destroy()}catch{}}),A=[],document.querySelectorAll("[data-chart]").forEach(t=>{let e;try{e=JSON.parse(t.dataset.config||"{}")}catch{return}if(!e.valores||!e.valores.length)return;const a=new M(t,nt(e));A.push(a)})}async function st(){if(document.querySelectorAll("[data-chart]").length)try{await ct(),H(),new MutationObserver(a=>{a.forEach(r=>{r.attributeName==="class"&&H()})}).observe(document.documentElement,{attributes:!0})}catch(e){console.warn("No se pudieron dibujar los gráficos.",e)}}/**
 * @license lucide v1.73.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const D={xmlns:"http://www.w3.org/2000/svg",width:24,height:24,viewBox:"0 0 24 24",fill:"none",stroke:"currentColor","stroke-width":2,"stroke-linecap":"round","stroke-linejoin":"round"};/**
 * @license lucide v1.73.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const U=([t,e,a])=>{const r=document.createElementNS("http://www.w3.org/2000/svg",t);return Object.keys(e).forEach(n=>{r.setAttribute(n,String(e[n]))}),a!=null&&a.length&&a.forEach(n=>{const c=U(n);r.appendChild(c)}),r},dt=(t,e={})=>{const r={...D,...e};return U(["svg",r,t])};/**
 * @license lucide v1.73.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const it=(...t)=>t.filter((e,a,r)=>!!e&&e.trim()!==""&&r.indexOf(e)===a).join(" ").trim();/**
 * @license lucide v1.73.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const lt=t=>{for(const e in t)if(e.startsWith("aria-")||e==="role"||e==="title")return!0;return!1};/**
 * @license lucide v1.73.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const ht=t=>{let e="",a=!1;for(const r of t){if(r==="-"||r==="_"||r<=" "){a=e.length>0;continue}e.length===0?e+=r.toLowerCase():e+=a?r.toUpperCase():r,a=!1}return e};/**
 * @license lucide v1.73.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const ut=t=>{const e=ht(t);return e.charAt(0).toUpperCase()+e.slice(1)};/**
 * @license lucide v1.73.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const pt=t=>Array.from(t.attributes).reduce((e,a)=>(e[a.name]=a.value,e),{}),O=t=>typeof t=="string"?t:!t||!t.class?"":t.class&&typeof t.class=="string"?t.class.split(" "):t.class&&Array.isArray(t.class)?t.class:"",P=(t,{nameAttr:e,icons:a,attrs:r})=>{var l;const n=t.getAttribute(e);if(n==null)return;const c=ut(n),s=a[c];if(!s)return console.warn(`${t.outerHTML} icon name was not found in the provided icons object.`);const o=pt(t),d=lt(o)?{}:{"aria-hidden":"true"},m={...D,"data-lucide":n,...d,...r,...o},i=O(o),h=O(r),u=it("lucide",`lucide-${n}`,...i,...h);u&&Object.assign(m,{class:u});const L=dt(s,m);return(l=t.parentNode)==null?void 0:l.replaceChild(L,t)};/**
 * @license lucide v1.73.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const mt=[["path",{d:"M22 12h-2.48a2 2 0 0 0-1.93 1.46l-2.35 8.36a.25.25 0 0 1-.48 0L9.24 2.18a.25.25 0 0 0-.48 0l-2.35 8.36A2 2 0 0 1 4.49 12H2"}]];/**
 * @license lucide v1.73.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const ft=[["path",{d:"M5 12h14"}],["path",{d:"m12 5 7 7-7 7"}]];/**
 * @license lucide v1.73.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const yt=[["path",{d:"m21 16-4 4-4-4"}],["path",{d:"M17 20V4"}],["path",{d:"m3 8 4-4 4 4"}],["path",{d:"M7 4v16"}]];/**
 * @license lucide v1.73.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const Mt=[["circle",{cx:"12",cy:"12",r:"10"}],["path",{d:"M4.929 4.929 19.07 19.071"}]];/**
 * @license lucide v1.73.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const vt=[["path",{d:"M10.268 21a2 2 0 0 0 3.464 0"}],["path",{d:"M3.262 15.326A1 1 0 0 0 4 17h16a1 1 0 0 0 .74-1.673C19.41 13.956 18 12.499 18 8A6 6 0 0 0 6 8c0 4.499-1.411 5.956-2.738 7.326"}]];/**
 * @license lucide v1.73.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const gt=[["path",{d:"M12 10h.01"}],["path",{d:"M12 14h.01"}],["path",{d:"M12 6h.01"}],["path",{d:"M16 10h.01"}],["path",{d:"M16 14h.01"}],["path",{d:"M16 6h.01"}],["path",{d:"M8 10h.01"}],["path",{d:"M8 14h.01"}],["path",{d:"M8 6h.01"}],["path",{d:"M9 22v-3a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v3"}],["rect",{x:"4",y:"2",width:"16",height:"20",rx:"2"}]];/**
 * @license lucide v1.73.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const wt=[["path",{d:"M8 2v3"}],["path",{d:"M16 2v3"}],["rect",{x:"3",y:"3",width:"18",height:"18",rx:"2"}],["path",{d:"M3 9h18"}],["path",{d:"m9 15 2 2 4-4"}]];/**
 * @license lucide v1.73.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const Et=[["path",{d:"M8 2v3"}],["path",{d:"M16 2v3"}],["rect",{x:"3",y:"3",width:"18",height:"18",rx:"2"}],["path",{d:"M3 9h18"}],["path",{d:"M8 13h.01"}],["path",{d:"M12 13h.01"}],["path",{d:"M16 13h.01"}],["path",{d:"M8 17h.01"}],["path",{d:"M12 17h.01"}],["path",{d:"M16 17h.01"}]];/**
 * @license lucide v1.73.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const bt=[["path",{d:"M16 18h6"}],["path",{d:"M16 2v3"}],["path",{d:"M19 15v6"}],["path",{d:"M21 11.5V5a2 2 0 00-2-2H5a2 2 0 00-2 2v14a2 2 0 002 2h8.3"}],["path",{d:"M3 9h18"}],["path",{d:"M8 2v3"}]];/**
 * @license lucide v1.73.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const xt=[["path",{d:"M8 2v3"}],["path",{d:"M16 2v3"}],["rect",{x:"3",y:"3",width:"18",height:"18",rx:"2"}],["path",{d:"M3 9h18"}]];/**
 * @license lucide v1.73.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const At=[["path",{d:"M3 3v16a2 2 0 0 0 2 2h16"}],["path",{d:"M18 17V9"}],["path",{d:"M13 17V5"}],["path",{d:"M8 17v-3"}]];/**
 * @license lucide v1.73.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const St=[["path",{d:"M20 6 9 17l-5-5"}]];/**
 * @license lucide v1.73.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const kt=[["path",{d:"m6 9 6 6 6-6"}]];/**
 * @license lucide v1.73.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const Ct=[["path",{d:"m15 18-6-6 6-6"}]];/**
 * @license lucide v1.73.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const Lt=[["path",{d:"m9 18 6-6-6-6"}]];/**
 * @license lucide v1.73.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const qt=[["path",{d:"m11 17-5-5 5-5"}],["path",{d:"m18 17-5-5 5-5"}]];/**
 * @license lucide v1.73.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const Tt=[["circle",{cx:"12",cy:"12",r:"10"}],["path",{d:"m16 9-5.5 5.5L8 12"}]];/**
 * @license lucide v1.73.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const Ht=[["circle",{cx:"12",cy:"12",r:"10"}],["line",{x1:"12",x2:"12",y1:"8",y2:"12"}],["line",{x1:"12",x2:"12.01",y1:"16",y2:"16"}]];/**
 * @license lucide v1.73.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const Ot=[["circle",{cx:"12",cy:"12",r:"10"}],["path",{d:"m15 9-6 6"}],["path",{d:"m9 9 6 6"}]];/**
 * @license lucide v1.73.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const Pt=[["circle",{cx:"12",cy:"12",r:"10"}]];/**
 * @license lucide v1.73.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const Vt=[["rect",{width:"8",height:"4",x:"8",y:"2",rx:"1",ry:"1"}],["path",{d:"M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"}],["path",{d:"m9 14 2 2 4-4"}]];/**
 * @license lucide v1.73.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const Rt=[["circle",{cx:"12",cy:"12",r:"10"}],["path",{d:"M12 6v6l4 2"}]];/**
 * @license lucide v1.73.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const It=[["path",{d:"M12 15V3"}],["path",{d:"M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"}],["path",{d:"m7 10 5 5 5-5"}]];/**
 * @license lucide v1.73.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const _t=[["circle",{cx:"12",cy:"12",r:"1"}],["circle",{cx:"19",cy:"12",r:"1"}],["circle",{cx:"5",cy:"12",r:"1"}]];/**
 * @license lucide v1.73.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const Nt=[["path",{d:"M15 3h6v6"}],["path",{d:"M10 14 21 3"}],["path",{d:"M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"}]];/**
 * @license lucide v1.73.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const jt=[["path",{d:"M2.062 12.348a1 1 0 0 1 0-.696 10.75 10.75 0 0 1 19.876 0 1 1 0 0 1 0 .696 10.75 10.75 0 0 1-19.876 0"}],["circle",{cx:"12",cy:"12",r:"3"}]];/**
 * @license lucide v1.73.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const Bt=[["path",{d:"M6 22a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h8a2.4 2.4 0 0 1 1.704.706l3.588 3.588A2.4 2.4 0 0 1 20 8v12a2 2 0 0 1-2 2z"}],["path",{d:"M14 2v5a1 1 0 0 0 1 1h5"}],["path",{d:"M8 13h2"}],["path",{d:"M14 13h2"}],["path",{d:"M8 17h2"}],["path",{d:"M14 17h2"}]];/**
 * @license lucide v1.73.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const Dt=[["path",{d:"M6 22a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h8a2.4 2.4 0 0 1 1.704.706l3.588 3.588A2.4 2.4 0 0 1 20 8v12a2 2 0 0 1-2 2z"}],["path",{d:"M14 2v5a1 1 0 0 0 1 1h5"}],["path",{d:"M10 9H8"}],["path",{d:"M16 13H8"}],["path",{d:"M16 17H8"}]];/**
 * @license lucide v1.73.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const Ut=[["path",{d:"M6 22a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h8a2.4 2.4 0 0 1 1.704.706l3.588 3.588A2.4 2.4 0 0 1 20 8v12a2 2 0 0 1-2 2z"}],["path",{d:"M14 2v5a1 1 0 0 0 1 1h5"}],["path",{d:"M12 12v6"}],["path",{d:"m15 15-3-3-3 3"}]];/**
 * @license lucide v1.73.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const zt=[["rect",{width:"18",height:"18",x:"3",y:"3",rx:"2"}],["path",{d:"M3 9h18"}],["path",{d:"M3 15h18"}],["path",{d:"M9 3v18"}],["path",{d:"M15 3v18"}]];/**
 * @license lucide v1.73.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const $t=[["path",{d:"M12 7v4"}],["path",{d:"M14 21v-3a2 2 0 0 0-4 0v3"}],["path",{d:"M14 9h-4"}],["path",{d:"M18 11h2a2 2 0 0 1 2 2v6a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2v-9a2 2 0 0 1 2-2h2"}],["path",{d:"M18 21V5a2 2 0 0 0-2-2H8a2 2 0 0 0-2 2v16"}]];/**
 * @license lucide v1.73.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const Ft=[["path",{d:"M5 22h14"}],["path",{d:"M5 2h14"}],["path",{d:"M17 22v-4.172a2 2 0 0 0-.586-1.414L12 12l-4.414 4.414A2 2 0 0 0 7 17.828V22"}],["path",{d:"M7 2v4.172a2 2 0 0 0 .586 1.414L12 12l4.414-4.414A2 2 0 0 0 17 6.172V2"}]];/**
 * @license lucide v1.73.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const Gt=[["path",{d:"M15 21v-8a1 1 0 0 0-1-1h-4a1 1 0 0 0-1 1v8"}],["path",{d:"M3 10a2 2 0 0 1 .709-1.528l7-6a2 2 0 0 1 2.582 0l7 6A2 2 0 0 1 21 10v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"}]];/**
 * @license lucide v1.73.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const Wt=[["polyline",{points:"22 12 16 12 14 15 10 15 8 12 2 12"}],["path",{d:"M5.45 5.11 2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z"}]];/**
 * @license lucide v1.73.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const Xt=[["rect",{width:"7",height:"9",x:"3",y:"3",rx:"1"}],["rect",{width:"7",height:"5",x:"14",y:"3",rx:"1"}],["rect",{width:"7",height:"9",x:"14",y:"12",rx:"1"}],["rect",{width:"7",height:"5",x:"3",y:"16",rx:"1"}]];/**
 * @license lucide v1.73.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const Yt=[["path",{d:"M11 5h10"}],["path",{d:"M11 12h10"}],["path",{d:"M11 19h10"}],["path",{d:"M4 4h1v5"}],["path",{d:"M4 9h2"}],["path",{d:"M6.5 20H3.4c0-1 2.6-1.925 2.6-3.5a1.5 1.5 0 0 0-2.6-1.02"}]];/**
 * @license lucide v1.73.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const Kt=[["path",{d:"M3 5h.01"}],["path",{d:"M3 12h.01"}],["path",{d:"M3 19h.01"}],["path",{d:"M8 5h13"}],["path",{d:"M8 12h13"}],["path",{d:"M8 19h13"}]];/**
 * @license lucide v1.73.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const Jt=[["rect",{width:"18",height:"11",x:"3",y:"11",rx:"2",ry:"2"}],["path",{d:"M7 11V7a5 5 0 0 1 10 0v4"}]];/**
 * @license lucide v1.73.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const Qt=[["path",{d:"m16 17 5-5-5-5"}],["path",{d:"M21 12H9"}],["path",{d:"M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"}]];/**
 * @license lucide v1.73.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const Zt=[["path",{d:"m22 7-8.991 5.727a2 2 0 0 1-2.009 0L2 7"}],["rect",{x:"2",y:"4",width:"20",height:"16",rx:"2"}]];/**
 * @license lucide v1.73.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const te=[["path",{d:"M4 5h16"}],["path",{d:"M4 12h16"}],["path",{d:"M4 19h16"}]];/**
 * @license lucide v1.73.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const ee=[["path",{d:"M5 12h14"}]];/**
 * @license lucide v1.73.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const ae=[["rect",{width:"20",height:"14",x:"2",y:"3",rx:"2"}],["line",{x1:"8",x2:"16",y1:"21",y2:"21"}],["line",{x1:"12",x2:"12",y1:"17",y2:"21"}]];/**
 * @license lucide v1.73.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const re=[["path",{d:"M20.985 12.486a9 9 0 1 1-9.473-9.472c.405-.022.617.46.402.803a6 6 0 0 0 8.268 8.268c.344-.215.825-.004.803.401"}]];/**
 * @license lucide v1.73.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const oe=[["rect",{width:"18",height:"18",x:"3",y:"3",rx:"2"}],["path",{d:"M9 3v18"}],["path",{d:"m16 15-3-3 3-3"}]];/**
 * @license lucide v1.73.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const ne=[["line",{x1:"19",x2:"5",y1:"5",y2:"19"}],["circle",{cx:"6.5",cy:"6.5",r:"2.5"}],["circle",{cx:"17.5",cy:"17.5",r:"2.5"}]];/**
 * @license lucide v1.73.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const ce=[["path",{d:"M21.174 6.812a1 1 0 0 0-3.986-3.987L3.842 16.174a2 2 0 0 0-.5.83l-1.321 4.352a.5.5 0 0 0 .623.622l4.353-1.32a2 2 0 0 0 .83-.497z"}],["path",{d:"m15 5 4 4"}]];/**
 * @license lucide v1.73.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const se=[["path",{d:"M5 12h14"}],["path",{d:"M12 5v14"}]];/**
 * @license lucide v1.73.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const de=[["path",{d:"M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"}],["path",{d:"M3 3v5h5"}]];/**
 * @license lucide v1.73.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const ie=[["path",{d:"m21 21-4.34-4.34"}],["circle",{cx:"11",cy:"11",r:"8"}]];/**
 * @license lucide v1.73.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const le=[["path",{d:"M14.536 21.686a.5.5 0 0 0 .937-.024l6.5-19a.496.496 0 0 0-.635-.635l-19 6.5a.5.5 0 0 0-.024.937l7.93 3.18a2 2 0 0 1 1.112 1.11z"}],["path",{d:"m21.854 2.147-10.94 10.939"}]];/**
 * @license lucide v1.73.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const V=[["path",{d:"M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"}],["path",{d:"m9 12 2 2 4-4"}]];/**
 * @license lucide v1.73.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const he=[["circle",{cx:"12",cy:"12",r:"4"}],["path",{d:"M12 2v2"}],["path",{d:"M12 20v2"}],["path",{d:"m4.93 4.93 1.41 1.41"}],["path",{d:"m17.66 17.66 1.41 1.41"}],["path",{d:"M2 12h2"}],["path",{d:"M20 12h2"}],["path",{d:"m6.34 17.66-1.41 1.41"}],["path",{d:"m19.07 4.93-1.41 1.41"}]];/**
 * @license lucide v1.73.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const ue=[["path",{d:"M12 3v18"}],["rect",{width:"18",height:"18",x:"3",y:"3",rx:"2"}],["path",{d:"M3 9h18"}],["path",{d:"M3 15h18"}]];/**
 * @license lucide v1.73.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const pe=[["path",{d:"m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3"}],["path",{d:"M12 9v4"}],["path",{d:"M12 17h.01"}]];/**
 * @license lucide v1.73.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const me=[["path",{d:"M12 3v12"}],["path",{d:"m17 8-5-5-5 5"}],["path",{d:"M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"}]];/**
 * @license lucide v1.73.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const fe=[["path",{d:"m16 11 2 2 4-4"}],["path",{d:"M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"}],["circle",{cx:"9",cy:"7",r:"4"}]];/**
 * @license lucide v1.73.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const ye=[["path",{d:"M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"}],["circle",{cx:"12",cy:"7",r:"4"}]];/**
 * @license lucide v1.73.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const Me=[["path",{d:"M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"}],["path",{d:"M16 3.128a4 4 0 0 1 0 7.744"}],["path",{d:"M22 21v-2a4 4 0 0 0-3-3.87"}],["circle",{cx:"9",cy:"7",r:"4"}]];/**
 * @license lucide v1.73.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const ve=[["path",{d:"M18 6 6 18"}],["path",{d:"m6 6 12 12"}]];/**
 * @license lucide v1.73.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const z=({icons:t={},nameAttr:e="data-lucide",attrs:a={},root:r=document,inTemplates:n}={})=>{if(!Object.values(t).length)throw new Error(`Please provide an icons object.
If you want to use all the icons you can import it like:
 \`import { createIcons, icons } from 'lucide';
lucide.createIcons({icons});\``);if(typeof r>"u")throw new Error("`createIcons()` only works in a browser environment.");if(Array.from(r.querySelectorAll(`[${e}]`)).forEach(s=>P(s,{nameAttr:e,icons:t,attrs:a})),n&&Array.from(r.querySelectorAll("template")).forEach(o=>z({icons:t,nameAttr:e,attrs:a,root:o.content,inTemplates:n})),e==="data-lucide"){const s=r.querySelectorAll("[icon-name]");s.length>0&&(console.warn("[Lucide] Some icons were found with the now deprecated icon-name attribute. These will still be replaced for backwards compatibility, but will no longer be supported in v1.0 and you should switch to data-lucide"),Array.from(s).forEach(o=>P(o,{nameAttr:"icon-name",icons:t,attrs:a})))}},ge={Activity:mt,ArrowRight:ft,ArrowUpDown:yt,Ban:Mt,Bell:vt,Building:gt,Calendar:xt,CalendarCheck:wt,CalendarDays:Et,CalendarPlus:bt,ChartColumn:At,Check:St,ChevronDown:kt,ChevronLeft:Ct,ChevronRight:Lt,ChevronsLeft:qt,Circle:Pt,CircleCheck:Tt,CircleExclamationPoint:Ht,CircleX:Ot,ClipboardCheck:Vt,ShieldCheck:V,Clock:Rt,Download:It,Ellipsis:_t,ExternalLink:Nt,Eye:jt,FileSpreadsheet:Bt,FileText:Dt,FileUp:Ut,Grid3x3:zt,Hospital:$t,Hourglass:Ft,House:Gt,Inbox:Wt,LayoutDashboard:Xt,List:Kt,ListOrdered:Yt,Lock:Jt,LogOut:Qt,Mail:Zt,Menu:te,Minus:ee,Monitor:ae,Moon:re,PanelLeftClose:oe,Pencil:ce,Percent:ne,Plus:se,RotateCcw:de,Search:ie,Send:le,Sun:he,Table:ue,TriangleExclamationPoint:pe,Upload:me,User:ye,UserCheck:fe,Users:Me,ShieldCheck:V,X:ve};function R(t=document){z({icons:ge,root:t})}function I(){F(),Y(),K(),et(),at(),st(),R(),window.remRenderIcons=R}document.readyState==="loading"?document.addEventListener("DOMContentLoaded",I,{once:!0}):I();
