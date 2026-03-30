import { useState, useEffect, useRef, useCallback, useMemo } from "react";
import * as PayrollAPI from "./payrollApi.js";

// Prime "P" logo as inline SVG data URI
const PRIME_LOGO = `data:image/svg+xml,${encodeURIComponent(`<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><rect width="100" height="100" fill="transparent"/><path d="M20 90V10h35c20 0 32 12 32 28s-12 28-32 28H38v24H20zm18-40h15c10 0 16-6 16-14s-6-14-16-14H38v28z" fill="#111" stroke="#111" stroke-width="3"/><path d="M26 84V16h29c17 0 26 10 26 22s-9 22-26 22H44v24H26zm18-40h11c7 0 12-4 12-10s-5-10-12-10H44v20z" fill="transparent" stroke="#111" stroke-width="1.5"/></svg>`)}`;

const uid = () => Math.random().toString(36).slice(2, 10);
const fmt$ = v => v ? "$" + Number(v).toLocaleString() : "â€”";
const pct = (a, b) => b === 0 ? "0%" : (a / b * 100).toFixed(1) + "%";
const nowT = () => { const d = new Date(); return d.toLocaleDateString("en-US", { month: "short", day: "numeric", year: "numeric" }) + " - " + d.toLocaleTimeString([], { hour: "2-digit", minute: "2-digit" }); };
const todayStr = () => new Date().toLocaleDateString();

// â”€â”€â”€ PERMISSIONS SYSTEM â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
const ALL_PERMISSIONS = [
  { key: "view_dashboard", label: "View Dashboard", group: "Views" },
  { key: "view_stats", label: "View Statistics", group: "Views" },
  { key: "view_leads", label: "View Leads", group: "Views" },
  { key: "view_pipeline", label: "View Pipeline", group: "Views" },
  { key: "view_deals", label: "View Deals", group: "Views" },
  { key: "view_verification", label: "View Verification", group: "Views" },
  { key: "view_chat", label: "View Chat", group: "Views" },
  { key: "view_users", label: "Manage Users", group: "Admin" },
  { key: "import_csv", label: "Import CSV Leads", group: "Leads" },
  { key: "add_leads", label: "Add Leads Manually", group: "Leads" },
  { key: "assign_leads", label: "Assign Leads", group: "Leads" },
  { key: "view_all_leads", label: "View All Leads", group: "Leads" },
  { key: "disposition_leads", label: "Disposition Leads", group: "Leads" },
  { key: "create_deals", label: "Create/Edit Deals", group: "Deals" },
  { key: "toggle_charged", label: "Toggle Charged Status", group: "Deals" },
  { key: "toggle_chargeback", label: "Toggle Chargeback Status", group: "Deals" },
  { key: "upload_files", label: "Upload PDFs & Files", group: "Files" },
  { key: "view_login_info", label: "View Deal Login Info", group: "Deals" },
  { key: "create_chats", label: "Create Chats/Groups", group: "Chat" },
  { key: "view_payroll", label: "View Payroll", group: "Payroll" },
  { key: "edit_payroll", label: "Edit Payroll Rates & Entries", group: "Payroll" },
  { key: "manage_payroll", label: "Send Paysheets & Export", group: "Payroll" },
  { key: "edit_users", label: "Edit User Roles & Permissions", group: "Admin" },
  { key: "delete_users", label: "Delete Users", group: "Admin" },
  { key: "master_override", label: "Master Override (All Access)", group: "Admin" },
];

const ROLE_DEFAULTS = {
  master_admin: ALL_PERMISSIONS.map(p => p.key),
  admin: ALL_PERMISSIONS.map(p => p.key).filter(k => k !== "master_override"),
  admin_limited: ["view_dashboard","view_leads","view_pipeline","view_deals","view_verification","view_chat","view_all_leads","assign_leads","import_csv","add_leads","toggle_charged","toggle_chargeback","view_login_info","create_deals","create_chats","view_payroll"],
  fronter: ["view_leads","view_pipeline","view_chat","disposition_leads","create_chats","view_payroll"],
  closer: ["view_dashboard","view_leads","view_pipeline","view_deals","view_verification","view_chat","disposition_leads","create_deals","create_chats","view_login_info","view_payroll"],
};

// â”€â”€â”€ USERS â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
const USERS_INIT = [
  { id:"u0", name:"David Chen", email:"david@tl.com", role:"master_admin", avatar:"DC", color:"#dc2626", status:"online", username:"dchen", password:"12345678", permissions: ROLE_DEFAULTS.master_admin },
  { id:"u9", name:"Angela Ross", email:"angela@tl.com", role:"master_admin", avatar:"AR", color:"#b91c1c", status:"online", username:"aross", password:"12345678", permissions: ROLE_DEFAULTS.master_admin },
  { id:"u1", name:"Mike Torres", email:"mike@tl.com", role:"admin", avatar:"MT", color:"#3b82f6", status:"online", username:"mtorres", password:"admin123", permissions: ROLE_DEFAULTS.admin },
  { id:"u2", name:"Sarah Chen", email:"sarah@tl.com", role:"admin_limited", avatar:"SC", color:"#10b981", status:"online", username:"schen", password:"admin456", permissions: ROLE_DEFAULTS.admin_limited },
  { id:"u3", name:"James Okafor", email:"james@tl.com", role:"fronter", avatar:"JO", color:"#ec4899", status:"online", username:"jokafor", password:"front123", permissions: ROLE_DEFAULTS.fronter },
  { id:"u4", name:"Dana Kim", email:"dana@tl.com", role:"fronter", avatar:"DK", color:"#f59e0b", status:"away", username:"dkim", password:"front456", permissions: ROLE_DEFAULTS.fronter },
  { id:"u7", name:"Tyler Brooks", email:"tyler@tl.com", role:"fronter", avatar:"TB", color:"#6366f1", status:"online", username:"tbrooks", password:"front789", permissions: ROLE_DEFAULTS.fronter },
  { id:"u5", name:"Marcus Rivera", email:"marcus@tl.com", role:"closer", avatar:"MR", color:"#8b5cf6", status:"online", username:"mrivera", password:"close123", permissions: ROLE_DEFAULTS.closer },
  { id:"u6", name:"Priya Sharma", email:"priya@tl.com", role:"closer", avatar:"PS", color:"#14b8a6", status:"online", username:"psharma", password:"close456", permissions: ROLE_DEFAULTS.closer },
  { id:"u8", name:"Alex Dominguez", email:"alex@tl.com", role:"closer", avatar:"AD", color:"#ef4444", status:"online", username:"adominguez", password:"close789", permissions: ROLE_DEFAULTS.closer },
];

const FRONTER_DISPOS = ["Wrong Number","Disconnected","Right Number","Left Voice Mail","Callback","Closed","Transferred to Closer"];
const CLOSER_DISPOS = ["Wrong Number","Disconnected","Right Number","Left Voice Mail","Callback","Closed","Transferred to Verification"];

// Deals with spread timestamps for weekly/monthly/quarterly/yearly stats
const DEALS_INIT = [
  { id:"d1", timestamp:"1/15/2026", chargedDate:"1/16/2026", wasVD:"No", fronter:"u3", closer:"u5", fee:"3995", ownerName:"John Smith", mailingAddress:"123 Main St", cityStateZip:"Orlando, FL 32819", primaryPhone:"4075551234", secondaryPhone:"4075555678", email:"jsmith@email.com", weeks:"2", askingRental:"1200", resortName:"Westgate Lakes Resort", resortCityState:"Orlando, FL", exchangeGroup:"RCI", bedBath:"2BR/2BA", usage:"Annual", askingSalePrice:"15000", nameOnCard:"John Smith", cardType:"Visa", bank:"Chase", cardNumber:"4111XXXX1234", expDate:"09/27", cv2:"***", billingAddress:"SAME", bank2:"", cardNumber2:"", expDate2:"", cv2_2:"", usingTimeshare:"No", lookingToGetOut:"Yes", verificationNum:"V-10042", notes:"Motivated seller, wants out ASAP", loginInfo:"Portal: westgate.com | User: jsmith_acct | Pass: ****", correspondence:["1/14 - Called, confirmed intent to sell","1/15 - Signed agreement, card processed","1/16 - Charged confirmed by bank"], files:[], snr:"", login:"portal.westgate.com", merchant:"Stripe-4821", appLogin:"jsmith_portal", assignedAdmin:"u1", status:"charged", charged:"yes", chargedBack:"no" },
  { id:"d2", timestamp:"1/22/2026", chargedDate:"1/23/2026", wasVD:"Yes", fronter:"u3", closer:"u5", fee:"2995", ownerName:"Linda Harmon", mailingAddress:"456 Oak Ave", cityStateZip:"Orlando, FL 32821", primaryPhone:"3215550142", secondaryPhone:"", email:"linda@email.com", weeks:"1", askingRental:"900", resortName:"Marriott Grande Vista", resortCityState:"Orlando, FL", exchangeGroup:"II", bedBath:"1BR/1BA", usage:"Annual", askingSalePrice:"8000", nameOnCard:"Linda Harmon", cardType:"Mastercard", bank:"BOA", cardNumber:"5200XXXX5678", expDate:"12/27", cv2:"***", billingAddress:"SAME", bank2:"", cardNumber2:"", expDate2:"", cv2_2:"", usingTimeshare:"No", lookingToGetOut:"Yes", verificationNum:"V-10043", notes:"Smooth close", loginInfo:"Marriott Vacations Portal | linda.h@mvw.com", correspondence:["1/20 - Initial contact via fronter","1/22 - Closed on first call"], files:[], snr:"", login:"", merchant:"", appLogin:"", assignedAdmin:"u1", status:"charged", charged:"yes", chargedBack:"no" },
  { id:"d3", timestamp:"2/05/2026", chargedDate:"2/06/2026", wasVD:"No", fronter:"u4", closer:"u6", fee:"4995", ownerName:"Robert Eng", mailingAddress:"789 Pine Rd", cityStateZip:"Gatlinburg, TN 37738", primaryPhone:"8285550167", secondaryPhone:"8285550168", email:"reng@email.com", weeks:"3", askingRental:"1500", resortName:"Hilton Grand Vacations", resortCityState:"Gatlinburg, TN", exchangeGroup:"RCI", bedBath:"3BR/2BA", usage:"Biennial", askingSalePrice:"22000", nameOnCard:"Robert Eng", cardType:"Visa", bank:"Wells Fargo", cardNumber:"4222XXXX9012", expDate:"06/28", cv2:"***", billingAddress:"SAME", bank2:"", cardNumber2:"", expDate2:"", cv2_2:"", usingTimeshare:"Yes", lookingToGetOut:"Yes", verificationNum:"V-10044", notes:"Large unit - chargeback after buyer remorse", loginInfo:"HGV Owner Portal | reng@hgv.com", correspondence:["2/3 - Transfer from Dana","2/5 - Closed","2/20 - Chargeback filed"], files:[], snr:"", login:"", merchant:"", appLogin:"", assignedAdmin:"u2", status:"chargeback", charged:"yes", chargedBack:"yes" },
  { id:"d4", timestamp:"2/18/2026", chargedDate:"2/19/2026", wasVD:"No", fronter:"u4", closer:"u6", fee:"1995", ownerName:"Carlos Mendes", mailingAddress:"321 Beach Blvd", cityStateZip:"Miami, FL 33101", primaryPhone:"7865550143", secondaryPhone:"", email:"carlos@email.com", weeks:"1", askingRental:"800", resortName:"Bluegreen Vacations", resortCityState:"Miami, FL", exchangeGroup:"RCI", bedBath:"1BR/1BA", usage:"Points", askingSalePrice:"5000", nameOnCard:"Carlos Mendes", cardType:"Amex", bank:"Citi", cardNumber:"3782XXXX3456", expDate:"03/28", cv2:"***", billingAddress:"SAME", bank2:"", cardNumber2:"", expDate2:"", cv2_2:"", usingTimeshare:"No", lookingToGetOut:"Yes", verificationNum:"V-10045", notes:"Quick close", loginInfo:"", correspondence:["2/17 - Transferred from Dana","2/18 - One-call close"], files:[], snr:"", login:"", merchant:"", appLogin:"", assignedAdmin:"u1", status:"charged", charged:"yes", chargedBack:"no" },
  { id:"d5", timestamp:"3/03/2026", chargedDate:"", wasVD:"No", fronter:null, closer:"u5", fee:"3495", ownerName:"Lisa Park", mailingAddress:"555 Lake Dr", cityStateZip:"Orlando, FL 32819", primaryPhone:"4075551122", secondaryPhone:"", email:"lisa@email.com", weeks:"2", askingRental:"1100", resortName:"Sheraton Flex", resortCityState:"Orlando, FL", exchangeGroup:"II", bedBath:"2BR/2BA", usage:"Annual", askingSalePrice:"12000", nameOnCard:"Lisa Park", cardType:"Visa", bank:"Chase", cardNumber:"4111XXXX7890", expDate:"11/27", cv2:"***", billingAddress:"SAME", bank2:"", cardNumber2:"", expDate2:"", cv2_2:"", usingTimeshare:"No", lookingToGetOut:"Yes", verificationNum:"", notes:"Self-sourced closer deal, pending admin review", loginInfo:"", correspondence:["3/2 - Self-sourced call","3/3 - Agreement signed"], files:[], snr:"", login:"", merchant:"", appLogin:"", assignedAdmin:"u2", status:"pending_admin", charged:"no", chargedBack:"no" },
  { id:"d6", timestamp:"3/10/2026", chargedDate:"3/11/2026", wasVD:"Yes", fronter:"u7", closer:"u8", fee:"2495", ownerName:"Kevin Moore", mailingAddress:"100 Sand Ln", cityStateZip:"Orlando, FL 32830", primaryPhone:"4075557777", secondaryPhone:"", email:"kevin@email.com", weeks:"1", askingRental:"700", resortName:"Club Wyndham Bonnet", resortCityState:"Orlando, FL", exchangeGroup:"RCI", bedBath:"1BR/1BA", usage:"Points", askingSalePrice:"6000", nameOnCard:"Kevin Moore", cardType:"Discover", bank:"Capital One", cardNumber:"6011XXXX2345", expDate:"08/27", cv2:"***", billingAddress:"SAME", bank2:"", cardNumber2:"", expDate2:"", cv2_2:"", usingTimeshare:"Yes", lookingToGetOut:"No", verificationNum:"V-10047", notes:"Charged back - disputed with bank", loginInfo:"Wyndham Portal | kmoore@wynd.com", correspondence:["3/9 - Tyler transferred","3/10 - Closed","3/14 - Chargeback notice"], files:[], snr:"", login:"", merchant:"", appLogin:"", assignedAdmin:"u1", status:"chargeback", charged:"yes", chargedBack:"yes" },
  { id:"d7", timestamp:"3/12/2026", chargedDate:"3/13/2026", wasVD:"No", fronter:"u7", closer:"u8", fee:"3995", ownerName:"Brenda White", mailingAddress:"200 Palm Way", cityStateZip:"Orlando, FL 32821", primaryPhone:"4075558888", secondaryPhone:"", email:"brenda@email.com", weeks:"2", askingRental:"1300", resortName:"Vistana Signature", resortCityState:"Orlando, FL", exchangeGroup:"II", bedBath:"2BR/2BA", usage:"Annual", askingSalePrice:"18000", nameOnCard:"Brenda White", cardType:"Visa", bank:"BOA", cardNumber:"4333XXXX6789", expDate:"05/28", cv2:"***", billingAddress:"SAME", bank2:"", cardNumber2:"", expDate2:"", cv2_2:"", usingTimeshare:"No", lookingToGetOut:"Yes", verificationNum:"V-10048", notes:"Great deal, clean close", loginInfo:"", correspondence:["3/11 - Transfer from Tyler","3/12 - Closed on callback"], files:[], snr:"", login:"", merchant:"", appLogin:"", assignedAdmin:"u2", status:"charged", charged:"yes", chargedBack:"no" },
  { id:"d8", timestamp:"3/15/2026", chargedDate:"3/16/2026", wasVD:"No", fronter:"u3", closer:"u5", fee:"5495", ownerName:"Teresa Grant", mailingAddress:"777 Bonnet Creek", cityStateZip:"Orlando, FL 32830", primaryPhone:"8435550134", secondaryPhone:"", email:"tgrant@email.com", weeks:"4", askingRental:"2000", resortName:"Wyndham Bonnet Creek", resortCityState:"Orlando, FL", exchangeGroup:"RCI", bedBath:"3BR/3BA", usage:"Annual", askingSalePrice:"35000", nameOnCard:"Teresa Grant", cardType:"Visa", bank:"Chase", cardNumber:"4555XXXX0123", expDate:"02/29", cv2:"***", billingAddress:"SAME", bank2:"", cardNumber2:"", expDate2:"", cv2_2:"", usingTimeshare:"Yes", lookingToGetOut:"Yes", verificationNum:"V-10049", notes:"Big ticket, 4 week annual", loginInfo:"Wyndham Owner Portal | tgrant2026", correspondence:["3/13 - James transferred","3/14 - Follow-up call","3/15 - Closed, card charged next day"], files:[], snr:"", login:"wyndham-owner.com", merchant:"Stripe-9921", appLogin:"tgrant_app", assignedAdmin:"u1", status:"charged", charged:"yes", chargedBack:"no" },
];

const LEADS_INIT = [
  { id:"l1", resort:"Westgate Lakes", ownerName:"John Smith", phone1:"4075551234", phone2:"4075555678", city:"Orlando", st:"FL", zip:"32819", resortLocation:"Orlando, FL", assignedTo:"u5", originalFronter:"u3", disposition:"Transferred to Closer", transferredTo:"u5", source:"manual", createdAt:"3/14/2026", callbackDate:null },
  { id:"l2", resort:"Marriott Grande Vista", ownerName:"Linda Harmon", phone1:"3215550142", phone2:"", city:"Orlando", st:"FL", zip:"32821", resortLocation:"Orlando, FL", assignedTo:"u5", originalFronter:"u3", disposition:"Transferred to Closer", transferredTo:"u5", source:"manual", createdAt:"3/14/2026", callbackDate:null },
  { id:"l3", resort:"Hilton Grand Vacations", ownerName:"Robert Eng", phone1:"8285550167", phone2:"8285550168", city:"Gatlinburg", st:"TN", zip:"37738", resortLocation:"Gatlinburg, TN", assignedTo:"u6", originalFronter:"u4", disposition:"Transferred to Closer", transferredTo:"u6", source:"manual", createdAt:"3/10/2026", callbackDate:null },
  { id:"l4", resort:"Wyndham Bonnet Creek", ownerName:"Teresa Grant", phone1:"8435550134", phone2:"", city:"Orlando", st:"FL", zip:"32830", resortLocation:"Orlando, FL", assignedTo:"u5", originalFronter:"u3", disposition:"Transferred to Closer", transferredTo:"u5", source:"manual", createdAt:"3/15/2026", callbackDate:null },
  { id:"l5", resort:"Bluegreen Vacations", ownerName:"Carlos Mendes", phone1:"7865550143", phone2:"7865550144", city:"Miami", st:"FL", zip:"33101", resortLocation:"Miami, FL", assignedTo:"u6", originalFronter:"u4", disposition:"Transferred to Closer", transferredTo:"u6", source:"manual", createdAt:"3/5/2026", callbackDate:null },
  { id:"l6", resort:"Sheraton Vistana", ownerName:"Amy Liu", phone1:"4075559876", phone2:"", city:"Orlando", st:"FL", zip:"32821", resortLocation:"Orlando, FL", assignedTo:"u3", originalFronter:"u3", disposition:"Wrong Number", transferredTo:null, source:"manual", createdAt:"3/3/2026", callbackDate:null },
  { id:"l7", resort:"Holiday Inn Club", ownerName:"Dave Patel", phone1:"3055551111", phone2:"", city:"Miami", st:"FL", zip:"33101", resortLocation:"Miami, FL", assignedTo:"u3", originalFronter:"u3", disposition:"Disconnected", transferredTo:null, source:"manual", createdAt:"2/28/2026", callbackDate:null },
  { id:"l8", resort:"Hyatt Residence", ownerName:"Nina Vasquez", phone1:"6155550189", phone2:"", city:"Nashville", st:"TN", zip:"37201", resortLocation:"Nashville, TN", assignedTo:"u3", originalFronter:"u3", disposition:"Callback", transferredTo:null, source:"manual", createdAt:"3/14/2026", callbackDate:"3/18/2026 2:00 PM" },
  { id:"l9", resort:"Wyndham Clearwater", ownerName:"Tom Richards", phone1:"7275553333", phone2:"", city:"Clearwater", st:"FL", zip:"33755", resortLocation:"Clearwater, FL", assignedTo:"u4", originalFronter:"u4", disposition:"Right Number", transferredTo:null, source:"manual", createdAt:"3/16/2026", callbackDate:null },
  { id:"l10", resort:"Westgate Town Ctr", ownerName:"Maria Gonzalez", phone1:"4075554444", phone2:"", city:"Kissimmee", st:"FL", zip:"34747", resortLocation:"Kissimmee, FL", assignedTo:"u4", originalFronter:"u4", disposition:"Wrong Number", transferredTo:null, source:"manual", createdAt:"2/25/2026", callbackDate:null },
  { id:"l11", resort:"Orange Lake Resort", ownerName:"Steve Brown", phone1:"4075555555", phone2:"", city:"Kissimmee", st:"FL", zip:"34747", resortLocation:"Kissimmee, FL", assignedTo:"u3", originalFronter:"u3", disposition:null, transferredTo:null, source:"csv", createdAt:"3/16/2026", callbackDate:null },
  { id:"l12", resort:"Marriott Harbour Lake", ownerName:"Rachel Adams", phone1:"4075556666", phone2:"", city:"Orlando", st:"FL", zip:"32821", resortLocation:"Orlando, FL", assignedTo:"u4", originalFronter:"u4", disposition:null, transferredTo:null, source:"csv", createdAt:"3/16/2026", callbackDate:null },
  { id:"l13", resort:"Club Wyndham Bonnet", ownerName:"Kevin Moore", phone1:"4075557777", phone2:"", city:"Orlando", st:"FL", zip:"32830", resortLocation:"Orlando, FL", assignedTo:"u8", originalFronter:"u7", disposition:"Transferred to Closer", transferredTo:"u8", source:"manual", createdAt:"3/9/2026", callbackDate:null },
  { id:"l14", resort:"Vistana Signature", ownerName:"Brenda White", phone1:"4075558888", phone2:"", city:"Orlando", st:"FL", zip:"32821", resortLocation:"Orlando, FL", assignedTo:"u8", originalFronter:"u7", disposition:"Transferred to Closer", transferredTo:"u8", source:"manual", createdAt:"3/11/2026", callbackDate:null },
  { id:"l15", resort:"Hilton Hawaiian", ownerName:"George Taylor", phone1:"8085559999", phone2:"", city:"Honolulu", st:"HI", zip:"96815", resortLocation:"Honolulu, HI", assignedTo:"u7", originalFronter:"u7", disposition:"Callback", transferredTo:null, source:"manual", createdAt:"3/15/2026", callbackDate:"3/19/2026 10:00 AM" },
  { id:"l16", resort:"Marriott Ko Olina", ownerName:"Pat Wilson", phone1:"8085550000", phone2:"", city:"Kapolei", st:"HI", zip:"96707", resortLocation:"Kapolei, HI", assignedTo:"u7", originalFronter:"u7", disposition:"Wrong Number", transferredTo:null, source:"manual", createdAt:"2/20/2026", callbackDate:null },
  { id:"l17", resort:"Sheraton Flex", ownerName:"Lisa Park", phone1:"4075551122", phone2:"", city:"Orlando", st:"FL", zip:"32819", resortLocation:"Orlando, FL", assignedTo:"u5", originalFronter:null, disposition:"Transferred to Verification", transferredTo:"verification", source:"manual", createdAt:"3/3/2026", callbackDate:null },
  { id:"l18", resort:"Wyndham Grand Desert", ownerName:"Frank Lopez", phone1:"7025553344", phone2:"", city:"Las Vegas", st:"NV", zip:"89109", resortLocation:"Las Vegas, NV", assignedTo:"u6", originalFronter:null, disposition:null, transferredTo:null, source:"manual", createdAt:"3/16/2026", callbackDate:null },
];

const CHATS_INIT = [
  { id:"ch1", type:"channel", name:"General", icon:"#", members:["u0","u9","u1","u2","u3","u4","u5","u6","u7","u8"], createdBy:"u0" },
  { id:"ch2", type:"channel", name:"Sales Floor", icon:"ðŸ’¼", members:["u0","u9","u1","u2","u3","u4","u5","u6","u7","u8"], createdBy:"u0" },
  { id:"ch3", type:"channel", name:"Closers Only", icon:"ðŸ”’", members:["u0","u9","u1","u2","u5","u6","u8"], createdBy:"u0" },
];

const MESSAGES_INIT = [
  { id:"m1", chatId:"ch1", userId:"u0", text:"Welcome to Prime CRM. Let's have a great quarter!", time:"Mar 14, 2026 - 9:00 AM", type:"text" },
  { id:"m2", chatId:"ch1", userId:"u3", text:"Got 3 transfers lined up today!", time:"Mar 14, 2026 - 9:15 AM", type:"text" },
  { id:"m3", chatId:"ch2", userId:"u5", text:"Just closed $3,995 - Westgate owner.", time:"Mar 14, 2026 - 10:30 AM", type:"text" },
  { id:"m4", chatId:"ch2", userId:"u1", text:"Great work Marcus!", time:"Mar 14, 2026 - 10:32 AM", type:"text" },
  { id:"m5", chatId:"ch1", userId:"u7", text:"Two transfers to Alex, both Orlando.", time:"Mar 15, 2026 - 10:45 AM", type:"text" },
  { id:"m6", chatId:"ch3", userId:"u8", text:"Kevin Moore charged. Brenda White looking good.", time:"Mar 16, 2026 - 11:00 AM", type:"text" },
];

// â”€â”€â”€ GIF SEARCH DATA (inline SVG-based GIF placeholders) â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
const makeGif = (emoji, label, bg="#f0f0f0") => `data:image/svg+xml,${encodeURIComponent(`<svg xmlns="http://www.w3.org/2000/svg" width="160" height="120"><rect width="160" height="120" rx="8" fill="${bg}"/><text x="80" y="55" text-anchor="middle" font-size="36">${emoji}</text><text x="80" y="85" text-anchor="middle" font-size="10" fill="#555" font-family="sans-serif">${label}</text></svg>`)}`;
const GIF_LIBRARY = {
  "Trending":[
    {id:"t1",url:makeGif("ðŸ”¥","Fire"),title:"Fire"},{id:"t2",url:makeGif("ðŸ’¯","100%","#e8e8e8"),title:"100"},{id:"t3",url:makeGif("ðŸŽ¯","Nailed It"),title:"Nailed It"},
    {id:"t4",url:makeGif("âš¡","Electric","#e8e8e8"),title:"Electric"},{id:"t5",url:makeGif("ðŸ™Œ","Praise"),title:"Praise"},{id:"t6",url:makeGif("ðŸ˜¤","Hustle","#e8e8e8"),title:"Hustle"},
  ],
  "Movies":[
    {id:"m1",url:makeGif("ðŸŽ¬","Action!"),title:"Lights Camera Action"},{id:"m2",url:makeGif("ðŸ¦","I'm The King","#e8e8e8"),title:"Lion King"},
    {id:"m3",url:makeGif("ðŸ§™","You Shall Not Pass"),title:"Gandalf"},{id:"m4",url:makeGif("ðŸ•¶ï¸","Deal With It","#e8e8e8"),title:"Deal With It"},
    {id:"m5",url:makeGif("ðŸ’Ž","King of the World"),title:"Titanic"},{id:"m6",url:makeGif("ðŸ¥Š","Gonna Fly Now","#e8e8e8"),title:"Rocky"},
    {id:"m7",url:makeGif("ðŸ¦ˆ","Bigger Boat"),title:"Jaws"},{id:"m8",url:makeGif("ðŸ§¤","Inevitable","#e8e8e8"),title:"Thanos Snap"},
    {id:"m9",url:makeGif("ðŸŽ©","An Offer..."),title:"Godfather"},{id:"m10",url:makeGif("ðŸº","Wolf of Wall St","#e8e8e8"),title:"Wolf of Wall Street"},
    {id:"m11",url:makeGif("ðŸƒ","Why So Serious"),title:"Joker"},{id:"m12",url:makeGif("ðŸŽï¸","I Am Speed","#e8e8e8"),title:"Lightning McQueen"},
  ],
  "TV Shows":[
    {id:"s1",url:makeGif("ðŸ“º","That's What She Said"),title:"Michael Scott"},{id:"s2",url:makeGif("ðŸ•","Joey Don't Share","#e8e8e8"),title:"Joey"},
    {id:"s3",url:makeGif("â˜•","How You Doin'"),title:"Joey Tribbiani"},{id:"s4",url:makeGif("ðŸ§ª","Say My Name","#e8e8e8"),title:"Heisenberg"},
    {id:"s5",url:makeGif("ðŸ‰","Dracarys"),title:"Daenerys"},{id:"s6",url:makeGif("ðŸ‘‘","You Know Nothing","#e8e8e8"),title:"Jon Snow"},
    {id:"s7",url:makeGif("ðŸ ","This Is Fine"),title:"Everything Fine"},{id:"s8",url:makeGif("ðŸ¤¯","Mind = Blown","#e8e8e8"),title:"Mind Blown"},
    {id:"s9",url:makeGif("ðŸ¦‡","I Am The Night"),title:"Batman"},{id:"s10",url:makeGif("ðŸ§Š","Cool Cool Cool","#e8e8e8"),title:"Brooklyn 99"},
    {id:"s11",url:makeGif("ðŸŒ®","Surprise!"),title:"The Office"},{id:"s12",url:makeGif("ðŸ“Ž","Dwight Mode","#e8e8e8"),title:"Dwight Schrute"},
  ],
  "Funny":[
    {id:"f1",url:makeGif("ðŸ˜‚","I'm Dead"),title:"I'm Dead"},{id:"f2",url:makeGif("ðŸ¤£","LMAO","#e8e8e8"),title:"LMAO"},
    {id:"f3",url:makeGif("ðŸ˜œ","Wink Wink"),title:"Wink"},{id:"f4",url:makeGif("ðŸ¤¡","Clown Moment","#e8e8e8"),title:"Clown"},
    {id:"f5",url:makeGif("ðŸ’€","Can't Even"),title:"Dead"},{id:"f6",url:makeGif("ðŸ« ","Melting","#e8e8e8"),title:"Melting"},
    {id:"f7",url:makeGif("ðŸ˜","If You Know..."),title:"Smirk"},{id:"f8",url:makeGif("ðŸ™ƒ","Totally Fine","#e8e8e8"),title:"Fine"},
    {id:"f9",url:makeGif("ðŸ˜Ž","Cool Story Bro"),title:"Cool"},{id:"f10",url:makeGif("ðŸ¤ª","Unhinged","#e8e8e8"),title:"Unhinged"},
    {id:"f11",url:makeGif("ðŸ˜…","Nervous Laugh"),title:"Nervous"},{id:"f12",url:makeGif("ðŸ¥´","Bruh Moment","#e8e8e8"),title:"Bruh"},
  ],
  "Reactions":[
    {id:"r1",url:makeGif("ðŸ‘","Slow Clap"),title:"Slow Clap"},{id:"r2",url:makeGif("ðŸ˜±","Shocked","#e8e8e8"),title:"Shocked"},
    {id:"r3",url:makeGif("ðŸ¤”","Hmm..."),title:"Thinking"},{id:"r4",url:makeGif("ðŸ‘€","Side Eye","#e8e8e8"),title:"Side Eye"},
    {id:"r5",url:makeGif("ðŸ«¡","Yes Sir"),title:"Salute"},{id:"r6",url:makeGif("ðŸ¤¦","Facepalm","#e8e8e8"),title:"Facepalm"},
    {id:"r7",url:makeGif("ðŸ’…","Unbothered"),title:"Unbothered"},{id:"r8",url:makeGif("ðŸ¥±","Boring","#e8e8e8"),title:"Boring"},
    {id:"r9",url:makeGif("ðŸ˜¤","Not Happy"),title:"Frustrated"},{id:"r10",url:makeGif("ðŸ™„","Eye Roll","#e8e8e8"),title:"Eye Roll"},
    {id:"r11",url:makeGif("ðŸ˜¬","Awkward"),title:"Yikes"},{id:"r12",url:makeGif("ðŸ«£","Peeking","#e8e8e8"),title:"Peeking"},
  ],
  "Celebrate":[
    {id:"c1",url:makeGif("ðŸŽ‰","Party Time"),title:"Party"},{id:"c2",url:makeGif("ðŸ†","Champion","#e8e8e8"),title:"Winner"},
    {id:"c3",url:makeGif("ðŸ’°","Cash Money"),title:"Money"},{id:"c4",url:makeGif("ðŸš€","To The Moon","#e8e8e8"),title:"Rocket"},
    {id:"c5",url:makeGif("ðŸ’ª","Let's Go"),title:"Strong"},{id:"c6",url:makeGif("ðŸ¥‚","Cheers","#e8e8e8"),title:"Cheers"},
    {id:"c7",url:makeGif("ðŸŽŠ","We Did It"),title:"Confetti"},{id:"c8",url:makeGif("ðŸ‘Š","Boom","#e8e8e8"),title:"Fist Bump"},
    {id:"c9",url:makeGif("â­","All Star"),title:"Star"},{id:"c10",url:makeGif("ðŸŽµ","Victory Song","#e8e8e8"),title:"Victory"},
  ],
  "Sales Floor":[
    {id:"w1",url:makeGif("ðŸ“ž","Pick Up!"),title:"Pick Up Phone"},{id:"w2",url:makeGif("ðŸ¤","CLOSED!","#e8e8e8"),title:"Deal Closed"},
    {id:"w3",url:makeGif("ðŸ“ˆ","Stonks"),title:"Stonks"},{id:"w4",url:makeGif("ðŸ’¼","Business","#e8e8e8"),title:"Strictly Business"},
    {id:"w5",url:makeGif("ðŸ””","Ring The Bell"),title:"Bell Ring"},{id:"w6",url:makeGif("â°","Grind Time","#e8e8e8"),title:"Grind"},
    {id:"w7",url:makeGif("ðŸ‘‘","Closer King"),title:"King"},{id:"w8",url:makeGif("ðŸ’¸","Get Paid","#e8e8e8"),title:"Paid"},
    {id:"w9",url:makeGif("ðŸŽ¤","Drop The Mic"),title:"Mic Drop"},{id:"w10",url:makeGif("ðŸ","GOAT","#e8e8e8"),title:"GOAT"},
  ],
};

// â”€â”€â”€ CSS â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
const CSS = `
@import url('https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@400;500&display=swap');
*{margin:0;padding:0;box-sizing:border-box}
:root{--bg-0:#ffffff;--bg-1:#f7f8fa;--bg-2:#eef0f4;--bg-3:#e2e5ea;--bg-4:#d4d8de;--border:#d1d5db;--border-h:#b0b5be;--t1:#111111;--t2:#4b5563;--t3:#9ca3af;--blue:#3b82f6;--blue-s:rgba(59,130,246,.12);--green:#10b981;--green-s:rgba(16,185,129,.12);--amber:#f59e0b;--amber-s:rgba(245,158,11,.12);--red:#ef4444;--red-s:rgba(239,68,68,.12);--purple:#8b5cf6;--purple-s:rgba(139,92,246,.12);--pink:#ec4899;--pink-s:rgba(236,72,153,.12);--teal:#14b8a6;--teal-s:rgba(20,184,166,.12);--crimson:#dc2626;--crimson-s:rgba(220,38,38,.12);--grad:linear-gradient(135deg,#111111,#333333);--grad-r:linear-gradient(135deg,#dc2626,#ef4444);--r:8px;--r-sm:5px;--r-lg:12px;--tr:.18s cubic-bezier(.4,0,.2,1)}
body,#root{font-family:'Outfit',sans-serif;background:var(--bg-0);color:var(--t1);height:100vh;overflow:hidden}
.login-screen{display:flex;align-items:center;justify-content:center;height:100vh;background:var(--bg-0)}
.login-box{background:var(--bg-2);border:1px solid var(--border);border-radius:var(--r-lg);padding:40px;width:420px;max-height:90vh;overflow-y:auto;text-align:center}
.login-box::-webkit-scrollbar{width:3px}.login-box::-webkit-scrollbar-thumb{background:var(--border-h);border-radius:3px}
.login-box h1{font-size:28px;font-weight:800;color:#111111;margin-bottom:4px;letter-spacing:2px}
.login-box p{color:var(--t3);font-size:13px;margin-bottom:24px}
.login-user{display:flex;align-items:center;gap:12px;padding:10px 14px;border-radius:var(--r);cursor:pointer;transition:var(--tr);border:1px solid transparent}
.login-user:hover{background:var(--bg-3);border-color:var(--border)}
.login-user .av{width:36px;height:36px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:600;color:#fff;flex-shrink:0}
.login-user .info{text-align:left;flex:1}
.login-user .nm{font-size:13px;font-weight:600}
.login-user .rl{font-size:11px;color:var(--t3);text-transform:capitalize}
.shell{display:grid;grid-template-columns:56px 1fr;height:100vh;overflow:hidden}
.rail{background:var(--bg-1);border-right:1px solid var(--border);display:flex;flex-direction:column;align-items:center;padding:14px 0;gap:2px}
.rail-logo{width:36px;height:36px;background:#ffffff;border-radius:var(--r);display:flex;align-items:center;justify-content:center;font-weight:800;font-size:14px;color:#111;margin-bottom:14px;box-shadow:0 1px 4px rgba(0,0,0,.1);overflow:hidden;border:1px solid var(--border)}
.rail-logo img{width:28px;height:28px;object-fit:contain}
.rail-btn{width:40px;height:40px;border:none;background:0;border-radius:var(--r);color:var(--t3);font-size:16px;cursor:pointer;display:flex;align-items:center;justify-content:center;transition:var(--tr);position:relative}
.rail-btn:hover{background:var(--bg-3);color:var(--t1)}
.rail-btn.on{background:var(--blue-s);color:var(--blue)}
.rail-btn .dot{position:absolute;top:6px;right:6px;width:7px;height:7px;background:var(--red);border-radius:50%;border:2px solid var(--bg-1)}
.rail-spacer{flex:1}
.rail-av{width:32px;height:32px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:600;color:#fff;cursor:pointer}
.main{overflow:hidden;display:flex;flex-direction:column}
.topbar{display:flex;align-items:center;gap:12px;padding:12px 20px;border-bottom:1px solid var(--border);background:var(--bg-1);flex-shrink:0}
.topbar h2{font-size:16px;font-weight:700;flex:1}
.topbar-role{font-size:11px;padding:3px 10px;border-radius:12px;font-weight:600;text-transform:uppercase;letter-spacing:.5px}
.content{flex:1;overflow:hidden;display:flex}
.panel{background:var(--bg-1);border-right:1px solid var(--border);display:flex;flex-direction:column;overflow:hidden;width:260px;flex-shrink:0}
.panel-hd{padding:14px 14px 10px;border-bottom:1px solid var(--border)}
.panel-hd h3{font-size:13px;font-weight:600;margin-bottom:8px}
.sbox{display:flex;align-items:center;gap:6px;background:var(--bg-2);border:1px solid var(--border);border-radius:var(--r-sm);padding:6px 10px}
.sbox:focus-within{border-color:var(--blue)}
.sbox input{flex:1;border:0;background:0;outline:0;color:var(--t1);font-size:12px;font-family:inherit}
.sbox input::placeholder{color:var(--t3)}
.plist{flex:1;overflow-y:auto;padding:6px}.plist::-webkit-scrollbar{width:3px}.plist::-webkit-scrollbar-thumb{background:var(--border-h);border-radius:3px}
.item{display:flex;align-items:center;gap:10px;padding:8px 10px;border-radius:var(--r-sm);cursor:pointer;transition:var(--tr);border:1px solid transparent;font-size:12px}
.item:hover{background:var(--bg-3)}
.item.on{background:var(--blue-s);border-color:rgba(59,130,246,.2)}
.item .av{width:30px;height:30px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:10px;font-weight:600;color:#fff;flex-shrink:0}
.item .inf{flex:1;min-width:0}
.item .nm{font-size:12px;font-weight:500;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.item .sub{font-size:10px;color:var(--t3);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.tag{display:inline-block;padding:2px 7px;border-radius:4px;font-size:9px;font-weight:600;text-transform:uppercase;letter-spacing:.3px}
.btn{padding:6px 14px;border-radius:var(--r-sm);border:1px solid var(--border);background:var(--bg-2);color:var(--t1);font-size:11px;font-weight:500;cursor:pointer;transition:var(--tr);font-family:inherit;display:inline-flex;align-items:center;gap:5px}
.btn:hover{background:var(--bg-3);border-color:var(--border-h)}
.btn-p{background:#111111;border-color:#111111;color:#fff}.btn-p:hover{background:#333333}
.btn-g{background:var(--green);border-color:var(--green);color:#fff}
.btn-d{background:var(--red);border-color:var(--red);color:#fff}
.btn-sm{padding:4px 10px;font-size:10px}
.detail{flex:1;overflow-y:auto;display:flex;flex-direction:column}.detail::-webkit-scrollbar{width:3px}.detail::-webkit-scrollbar-thumb{background:var(--border-h);border-radius:3px}
.det-hd{padding:16px 20px;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:flex-start;flex-shrink:0}
.det-hd h2{font-size:18px;font-weight:700}
.det-hd .sub{font-size:12px;color:var(--t2);margin-top:2px}
.det-body{flex:1;overflow-y:auto;padding:20px}.det-body::-webkit-scrollbar{width:3px}.det-body::-webkit-scrollbar-thumb{background:var(--border-h);border-radius:3px}
.igrid{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:20px}
.igrid.c3{grid-template-columns:1fr 1fr 1fr}
.igrid.c4{grid-template-columns:1fr 1fr 1fr 1fr}
.icard{background:var(--bg-2);border:1px solid var(--border);border-radius:var(--r);padding:12px}
.icard .lbl{font-size:10px;color:var(--t3);text-transform:uppercase;letter-spacing:.4px;margin-bottom:3px}
.icard .val{font-size:13px;font-weight:500}.icard .val.lg{font-size:20px;font-weight:700}.icard .val.xl{font-size:26px;font-weight:800}.icard .val.mono{font-family:'JetBrains Mono',monospace;font-size:12px}
.sec-title{font-size:11px;font-weight:600;color:var(--t3);text-transform:uppercase;letter-spacing:.5px;margin:16px 0 8px}
.form-grid{display:grid;grid-template-columns:1fr 1fr;gap:10px}.form-grid.c3{grid-template-columns:1fr 1fr 1fr}.form-grid.c1{grid-template-columns:1fr}
.fg{display:flex;flex-direction:column;gap:3px}.fg.span2{grid-column:span 2}
.fg label{font-size:10px;color:var(--t3);text-transform:uppercase;letter-spacing:.3px;font-weight:500}
.fg input,.fg select,.fg textarea{background:var(--bg-2);border:1px solid var(--border);border-radius:var(--r-sm);padding:7px 10px;color:var(--t1);font-size:12px;font-family:inherit;outline:0;transition:var(--tr)}
.fg input:focus,.fg select:focus,.fg textarea:focus{border-color:var(--blue)}
.fg textarea{resize:vertical;min-height:60px}.fg select{cursor:pointer}
.chat-area{flex:1;display:flex;flex-direction:column;overflow:hidden}
.chat-hd{padding:12px 20px;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:10px;flex-shrink:0}
.chat-hd h3{font-size:14px;font-weight:600}
.chat-msgs{flex:1;overflow-y:auto;padding:14px 20px;display:flex;flex-direction:column;gap:2px}.chat-msgs::-webkit-scrollbar{width:3px}.chat-msgs::-webkit-scrollbar-thumb{background:var(--border-h);border-radius:3px}
.msg-row{display:flex;gap:10px;padding:6px 0}
.msg-av{width:28px;height:28px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:10px;font-weight:600;color:#fff;flex-shrink:0;margin-top:2px}
.msg-body{flex:1}
.msg-nm{font-size:12px;font-weight:600}.msg-nm span{font-weight:400;color:var(--t3);font-size:10px;margin-left:6px}
.msg-txt{font-size:12px;color:var(--t2);line-height:1.5;margin-top:1px}
.msg-gif{max-width:200px;border-radius:var(--r);margin-top:4px}
.msg-file{display:inline-flex;align-items:center;gap:6px;padding:6px 12px;background:var(--bg-3);border:1px solid var(--border);border-radius:var(--r);margin-top:4px;font-size:11px;color:var(--blue);cursor:pointer}
.chat-inp{padding:12px 20px;border-top:1px solid var(--border);flex-shrink:0}
.chat-inp-box{display:flex;align-items:center;gap:8px;background:var(--bg-2);border:1px solid var(--border);border-radius:var(--r);padding:8px 14px}.chat-inp-box:focus-within{border-color:var(--blue)}
.chat-inp-box input{flex:1;border:0;background:0;outline:0;color:var(--t1);font-size:12px;font-family:inherit}.chat-inp-box input::placeholder{color:var(--t3)}
.chat-tool-btn{width:28px;height:28px;border-radius:50%;border:1px solid var(--border);background:var(--bg-3);color:var(--t2);cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:12px;transition:var(--tr)}.chat-tool-btn:hover{background:var(--bg-4);color:var(--t1);border-color:var(--blue)}
.send-btn{width:28px;height:28px;border-radius:50%;border:0;background:#111111;color:#fff;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:13px;transition:var(--tr)}.send-btn:hover{background:#333}
.gif-picker{position:absolute;bottom:60px;left:20px;right:20px;background:var(--bg-1);border:1px solid var(--border);border-radius:var(--r-lg);z-index:50;max-height:360px;display:flex;flex-direction:column;box-shadow:0 4px 20px rgba(0,0,0,.1)}
.gif-cats{display:flex;gap:2px;padding:8px 12px;border-bottom:1px solid var(--border);overflow-x:auto;flex-shrink:0}
.gif-cats::-webkit-scrollbar{height:0}
.gif-cat{padding:4px 10px;border-radius:12px;font-size:10px;font-weight:600;cursor:pointer;white-space:nowrap;background:var(--bg-2);color:var(--t3);border:1px solid transparent;transition:var(--tr)}
.gif-cat:hover{color:var(--t1);background:var(--bg-3)}
.gif-cat.on{background:#111;color:#fff;border-color:#111}
.gif-search{padding:8px 12px;border-bottom:1px solid var(--border);flex-shrink:0}
.gif-search input{width:100%;background:var(--bg-2);border:1px solid var(--border);border-radius:var(--r-sm);padding:6px 10px;color:var(--t1);font-size:11px;font-family:inherit;outline:0}
.gif-search input:focus{border-color:var(--border-h)}
.gif-grid{flex:1;overflow-y:auto;padding:8px 12px;display:grid;grid-template-columns:repeat(3,1fr);gap:6px}
.gif-grid::-webkit-scrollbar{width:3px}.gif-grid::-webkit-scrollbar-thumb{background:var(--border-h);border-radius:3px}
.gif-grid img{width:100%;border-radius:var(--r-sm);cursor:pointer;transition:var(--tr)}.gif-grid img:hover{opacity:.8;transform:scale(1.03)}
.modal-overlay{position:fixed;inset:0;background:rgba(0,0,0,.6);display:flex;align-items:center;justify-content:center;z-index:100;backdrop-filter:blur(4px)}
.modal{background:var(--bg-2);border:1px solid var(--border);border-radius:var(--r-lg);padding:24px;width:90%;max-width:560px;max-height:85vh;overflow-y:auto}.modal::-webkit-scrollbar{width:3px}.modal::-webkit-scrollbar-thumb{background:var(--border-h);border-radius:3px}
.modal h3{font-size:16px;font-weight:700;margin-bottom:16px}
.modal-actions{display:flex;gap:8px;justify-content:flex-end;margin-top:16px}
.tbl-wrap{overflow-x:auto;flex:1}
.tbl{width:100%;border-collapse:collapse;font-size:12px}
.tbl th{text-align:left;padding:8px 12px;border-bottom:1px solid var(--border);color:var(--t3);font-size:10px;text-transform:uppercase;letter-spacing:.4px;font-weight:600;position:sticky;top:0;background:var(--bg-1);z-index:1}
.tbl td{padding:8px 12px;border-bottom:1px solid var(--border)}
.tbl tr:hover td{background:var(--bg-3)}
.tabs{display:flex;gap:2px;padding:0 20px;border-bottom:1px solid var(--border);flex-shrink:0;overflow-x:auto}
.tab{padding:10px 14px;font-size:12px;font-weight:500;color:var(--t3);cursor:pointer;border-bottom:2px solid transparent;transition:var(--tr);white-space:nowrap}.tab:hover{color:var(--t1)}.tab.on{color:var(--blue);border-bottom-color:var(--blue)}
.empty{display:flex;flex-direction:column;align-items:center;justify-content:center;height:100%;color:var(--t3);gap:6px;padding:40px}.empty .icon{font-size:32px;opacity:.4}.empty .txt{font-size:13px}
@keyframes fadeIn{from{opacity:0;transform:translateY(6px)}to{opacity:1;transform:translateY(0)}}.fin{animation:fadeIn .25s ease forwards}
@keyframes pulse{0%{transform:scale(1);opacity:1}50%{transform:scale(1.4);opacity:.7}100%{transform:scale(1);opacity:1}}
.dispo-bar{display:flex;flex-wrap:wrap;gap:6px;margin-top:12px}
.dispo-btn{padding:5px 12px;border-radius:var(--r-sm);border:1px solid var(--border);background:var(--bg-2);color:var(--t2);font-size:11px;cursor:pointer;transition:var(--tr);font-family:inherit;font-weight:500}
.dispo-btn:hover{border-color:var(--blue);color:var(--t1);background:var(--blue-s)}
.dispo-btn.wr{border-color:var(--red);color:var(--red);background:var(--red-s)}
.dispo-btn.dc{border-color:var(--amber);color:var(--amber);background:var(--amber-s)}
.dispo-btn.rn{border-color:var(--green);color:var(--green);background:var(--green-s)}
.dispo-btn.lv{border-color:var(--purple);color:var(--purple);background:var(--purple-s)}
.dispo-btn.cl{border-color:var(--teal);color:var(--teal);background:var(--teal-s)}
.dispo-btn.tr{border-color:var(--pink);color:var(--pink);background:var(--pink-s)}
.dispo-btn.cb{border-color:#2563eb;color:#2563eb;background:rgba(37,99,235,.1)}
.stat-bar{height:8px;border-radius:4px;background:var(--bg-3);overflow:hidden;margin-top:6px}
.stat-fill{height:100%;border-radius:4px;transition:width .5s ease}
.perm-grid{display:grid;grid-template-columns:1fr 1fr;gap:6px}
.perm-item{display:flex;align-items:center;gap:8px;padding:6px 10px;border-radius:var(--r-sm);background:var(--bg-3);font-size:11px;cursor:pointer;transition:var(--tr);border:1px solid transparent}
.perm-item:hover{border-color:var(--border-h)}
.perm-item.on{background:var(--blue-s);border-color:rgba(59,130,246,.3)}
.perm-check{width:16px;height:16px;border-radius:3px;border:1px solid var(--border);display:flex;align-items:center;justify-content:center;font-size:10px;color:var(--blue);flex-shrink:0}
.perm-item.on .perm-check{background:var(--blue);border-color:var(--blue);color:#fff}
.corr-item{padding:8px 12px;background:var(--bg-3);border-radius:var(--r-sm);margin-bottom:6px;font-size:12px;color:var(--t2);line-height:1.5;border-left:3px solid var(--blue)}
.period-card{text-align:center;padding:16px;background:var(--bg-2);border:1px solid var(--border);border-radius:var(--r)}
.period-card .v{font-size:24px;font-weight:800;font-family:'JetBrains Mono',monospace}
.period-card .ct{font-size:12px;color:var(--t3);margin-top:2px}
.period-card .l{font-size:10px;color:var(--t3);text-transform:uppercase;letter-spacing:.4px;margin-bottom:6px}
`;

const ROLE_LABELS = { master_admin:"Master Admin", admin:"Admin", admin_limited:"Admin (Limited)", fronter:"Fronter", closer:"Closer" };
const ROLE_COLORS = { master_admin:"var(--crimson)", admin:"var(--blue)", admin_limited:"var(--teal)", fronter:"var(--pink)", closer:"var(--purple)" };

const hasPerm = (user, perm) => user?.permissions?.includes(perm) || user?.permissions?.includes("master_override");

// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
// MAIN APP
// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
export default function CRM() {
  const [currentUser, setCurrentUser] = useState(null);
  const [users, setUsers] = useState(USERS_INIT);
  const [leads, setLeads] = useState(LEADS_INIT);
  const [deals, setDeals] = useState(DEALS_INIT);
  const [chats, setChats] = useState(CHATS_INIT);
  const [messages, setMessages] = useState(MESSAGES_INIT);
  const [view, setView] = useState("dashboard");
  const [selectedLead, setSelectedLead] = useState(null);
  const [selectedDeal, setSelectedDeal] = useState(null);
  const [selectedChat, setSelectedChat] = useState("ch1");
  const [modal, setModal] = useState(null);
  const [transferLog, setTransferLog] = useState([
    { id:"tr1", type:"fronter_to_closer", from:"u3", to:"u5", leadId:"l1", leadName:"John Smith", timestamp:"Mar 10, 2026 - 9:30 AM" },
    { id:"tr2", type:"fronter_to_closer", from:"u4", to:"u6", leadId:"l3", leadName:"Robert Eng", timestamp:"Mar 11, 2026 - 2:15 PM" },
    { id:"tr3", type:"closer_to_admin", from:"u5", to:"u1", dealId:"d1", dealName:"John Smith", amount:"3995", timestamp:"Mar 12, 2026 - 10:00 AM" },
    { id:"tr4", type:"closer_to_admin", from:"u8", to:"u1", dealId:"d6", dealName:"Kevin Moore", amount:"2495", timestamp:"Mar 14, 2026 - 11:30 AM" },
  ]);
  const [notifications, setNotifications] = useState([]);
  const [bulkSelected, setBulkSelected] = useState([]);
  const [tasks, setTasks] = useState([
    { id:"tk1", title:"Call John Smith - verify address", type:"client", assignedTo:"u5", createdBy:"u1", dealId:"d1", leadId:"l1", clientName:"John Smith", status:"open", priority:"high", dueDate:"3/18/2026", createdAt:"3/15/2026", notes:[{ text:"Created task for address verification before charge", by:"u1", time:"Mar 15, 2026 - 10:00 AM" }] },
    { id:"tk2", title:"Login to Westgate portal and confirm ownership", type:"login", assignedTo:"u1", createdBy:"u0", dealId:"d1", leadId:null, clientName:"John Smith", status:"open", priority:"medium", dueDate:"3/17/2026", createdAt:"3/15/2026", notes:[{ text:"Need to verify ownership status on portal", by:"u0", time:"Mar 15, 2026 - 11:00 AM" }] },
    { id:"tk3", title:"Follow up with Linda Harmon on contract", type:"deal", assignedTo:"u5", createdBy:"u1", dealId:"d2", leadId:null, clientName:"Linda Harmon", status:"completed", priority:"low", dueDate:"3/14/2026", createdAt:"3/12/2026", notes:[{ text:"Send follow-up email", by:"u1", time:"Mar 12, 2026 - 2:00 PM" },{ text:"Emailed client, confirmed receipt of contract", by:"u5", time:"Mar 14, 2026 - 9:30 AM" }], completedAt:"Mar 14, 2026 - 9:30 AM" },
    { id:"tk4", title:"Add notes from Robert Eng callback", type:"notes", assignedTo:"u4", createdBy:"u2", dealId:null, leadId:"l3", clientName:"Robert Eng", status:"open", priority:"high", dueDate:"3/17/2026", createdAt:"3/16/2026", notes:[{ text:"Client called back requesting info on cancellation", by:"u2", time:"Mar 16, 2026 - 8:00 AM" }] },
  ]);
  const [authLoaded, setAuthLoaded] = useState(false);
  const [crmName, setCrmName] = useState("PRIME CRM");
  const [dealStatuses, setDealStatuses] = useState([
    { id: "pending_admin", label: "Pending Admin", color: "var(--amber)" },
    { id: "in_verification", label: "In Verification", color: "var(--blue)" },
    { id: "charged", label: "Charged", color: "var(--green)" },
    { id: "chargeback", label: "Chargeback", color: "var(--red)" },
    { id: "chargeback_won", label: "Chargeback Won", color: "#10b981" },
    { id: "chargeback_lost", label: "Chargeback Lost", color: "#991b1b" },
    { id: "cancelled", label: "Cancelled", color: "var(--t3)" },
  ]);
  const [crmTheme, setCrmTheme] = useState("light");
  const [chatOpen, setChatOpen] = useState(false);
  const [chatMinimized, setChatMinimized] = useState(false);
  const [chatPos, setChatPos] = useState({ x: null, y: null });
  const [chatSize, setChatSize] = useState({ w: 420, h: 560 });
  const chatDrag = useRef(null);
  const chatResize = useRef(null);
  const prevMsgCount = useRef(0);
  const lastSeenMsgId = useRef(null);

  // Track seen messages when chat is open and not minimized
  useEffect(() => {
    if (chatOpen && !chatMinimized && messages.length > 0) {
      lastSeenMsgId.current = messages[messages.length - 1].id;
    }
  }, [chatOpen, chatMinimized, messages.length]);

  const onDragStart = (e) => {
    if (chatMinimized) return;
    const rect = e.currentTarget.closest("[data-chat-popup]").getBoundingClientRect();
    chatDrag.current = { startX: e.clientX, startY: e.clientY, origX: rect.left, origY: rect.top };
    const onMove = (ev) => {
      if (!chatDrag.current) return;
      const dx = ev.clientX - chatDrag.current.startX;
      const dy = ev.clientY - chatDrag.current.startY;
      setChatPos({ x: chatDrag.current.origX + dx, y: chatDrag.current.origY + dy });
    };
    const onUp = () => { chatDrag.current = null; window.removeEventListener("mousemove", onMove); window.removeEventListener("mouseup", onUp); };
    window.addEventListener("mousemove", onMove);
    window.addEventListener("mouseup", onUp);
  };

  const onResizeStart = (e) => {
    e.stopPropagation(); e.preventDefault();
    chatResize.current = { startX: e.clientX, startY: e.clientY, origW: chatSize.w, origH: chatSize.h };
    const onMove = (ev) => {
      if (!chatResize.current) return;
      const dw = ev.clientX - chatResize.current.startX;
      const dh = chatResize.current.startY - ev.clientY;
      setChatSize({ w: Math.max(320, Math.min(800, chatResize.current.origW + dw)), h: Math.max(300, Math.min(900, chatResize.current.origH + dh)) });
    };
    const onUp = () => { chatResize.current = null; window.removeEventListener("mousemove", onMove); window.removeEventListener("mouseup", onUp); };
    window.addEventListener("mousemove", onMove);
    window.addEventListener("mouseup", onUp);
  };

  // Notification sound generator
  const playBing = useCallback((urgent) => {
    try {
      const ctx = new (window.AudioContext || window.webkitAudioContext)();
      const osc = ctx.createOscillator();
      const gain = ctx.createGain();
      osc.connect(gain); gain.connect(ctx.destination);
      osc.type = "sine";
      if (urgent) {
        osc.frequency.setValueAtTime(1200, ctx.currentTime);
        osc.frequency.setValueAtTime(1500, ctx.currentTime + 0.1);
        osc.frequency.setValueAtTime(1200, ctx.currentTime + 0.2);
        osc.frequency.setValueAtTime(1500, ctx.currentTime + 0.3);
        gain.gain.setValueAtTime(0.5, ctx.currentTime);
        gain.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + 0.5);
        osc.start(ctx.currentTime); osc.stop(ctx.currentTime + 0.5);
      } else {
        osc.frequency.setValueAtTime(880, ctx.currentTime);
        osc.frequency.setValueAtTime(1100, ctx.currentTime + 0.08);
        gain.gain.setValueAtTime(0.3, ctx.currentTime);
        gain.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + 0.3);
        osc.start(ctx.currentTime); osc.stop(ctx.currentTime + 0.3);
      }
    } catch (e) {}
  }, []);

  // Play bing on new messages, louder ding if @mentioned
  useEffect(() => {
    if (messages.length > prevMsgCount.current && prevMsgCount.current > 0) {
      const lastMsg = messages[messages.length - 1];
      if (lastMsg.userId !== currentUser?.id) {
        const isMentioned = lastMsg.text && currentUser?.name && lastMsg.text.includes("@" + currentUser.name);
        playBing(isMentioned);
      }
    }
    prevMsgCount.current = messages.length;
  }, [messages.length]);

  const THEMES = {
    light: { bg0:"#ffffff",bg1:"#f7f8fa",bg2:"#eef0f4",bg3:"#e2e5ea",bg4:"#d4d8de",border:"#d1d5db",borderH:"#b0b5be",t1:"#111111",t2:"#4b5563",t3:"#9ca3af",grad:"linear-gradient(135deg,#111111,#333333)" },
    dark: { bg0:"#0a0e17",bg1:"#0f1520",bg2:"#151d2e",bg3:"#1c2840",bg4:"#253454",border:"#1e2d48",borderH:"#2d4166",t1:"#e8ecf2",t2:"#8b9bb5",t3:"#5a6f8a",grad:"linear-gradient(135deg,#3b82f6,#8b5cf6)" },
    blue: { bg0:"#f0f4ff",bg1:"#e6edff",bg2:"#d4e0ff",bg3:"#bdd0ff",bg4:"#a3bcff",border:"#b8cbee",borderH:"#8ba8d9",t1:"#0f1a2e",t2:"#3d5278",t3:"#7b92b5",grad:"linear-gradient(135deg,#2563eb,#4f46e5)" },
    green: { bg0:"#f0fdf4",bg1:"#e6f9ec",bg2:"#d1f2dc",bg3:"#b8e8c9",bg4:"#9ddcb4",border:"#a7d5b8",borderH:"#6fb88c",t1:"#0a1f12",t2:"#2d5a3e",t3:"#6b9a7e",grad:"linear-gradient(135deg,#059669,#10b981)" },
  };

  // Auto-login: load saved session on mount
  useEffect(() => {
    (async () => {
      try {
        const saved = await window.storage.get("prime_crm_session");
        if (saved && saved.value) {
          const session = JSON.parse(saved.value);
          const u = USERS_INIT.find(x => x.username === session.username && x.password === session.password);
          if (u) {
            setCurrentUser(u);
            setView(hasPerm(u, "view_dashboard") ? "dashboard" : hasPerm(u, "view_leads") ? "leads" : "chat");
          }
        }
      } catch (e) { /* no saved session */ }
      setAuthLoaded(true);
    })();
  }, []);

  // Save session on login
  const loginAndSave = (u) => {
    setCurrentUser(u);
    setView(hasPerm(u, "view_dashboard") ? "dashboard" : hasPerm(u, "view_leads") ? "leads" : "chat");
    try { window.storage.set("prime_crm_session", JSON.stringify({ username: u.username, password: u.password })); } catch (e) {}
  };

  // Logout and clear session
  const logout = () => {
    setCurrentUser(null);
    try { window.storage.delete("prime_crm_session"); } catch (e) {}
  };

  const [loginUser, setLoginUser] = useState("");
  const [loginPass, setLoginPass] = useState("");
  const [loginError, setLoginError] = useState("");

  const doLogin = () => {
    const u = users.find(x => x.username === loginUser && x.password === loginPass);
    if (u) { loginAndSave(u); setLoginError(""); }
    else setLoginError("Invalid username or password");
  };

  if (!authLoaded) return (
    <><style>{CSS}</style>
    <div className="login-screen"><div className="login-box fin" style={{ padding: 60 }}>
      <img src={PRIME_LOGO} alt="Prime" style={{ width: 60, height: 60, marginBottom: 16 }} />
      <div style={{ fontSize: 14, color: "var(--t3)" }}>Loading...</div>
    </div></div></>
  );

  if (!currentUser) return (
    <><style>{CSS}</style>
    <div className="login-screen"><div className="login-box fin">
      <div style={{ marginBottom: 20 }}><img src={PRIME_LOGO} alt="Prime" style={{ width: 60, height: 60 }} /></div>
      <h1>{crmName}</h1><p>Enter your credentials to log in</p>
      <div style={{ textAlign: "left", marginBottom: 12 }}>
        <div className="fg" style={{ marginBottom: 10 }}><label>Username</label><input value={loginUser} onChange={e => { setLoginUser(e.target.value); setLoginError(""); }} placeholder="Enter username" style={{ background: "var(--bg-2)", border: "1px solid var(--border)", borderRadius: "var(--r-sm)", padding: "10px 12px", color: "var(--t1)", fontSize: 13, fontFamily: "inherit", outline: 0, width: "100%" }} /></div>
        <div className="fg"><label>Password (8 digit)</label><input type="password" value={loginPass} onChange={e => { setLoginPass(e.target.value); setLoginError(""); }} placeholder="Enter password" style={{ background: "var(--bg-2)", border: "1px solid var(--border)", borderRadius: "var(--r-sm)", padding: "10px 12px", color: "var(--t1)", fontSize: 13, fontFamily: "inherit", outline: 0, width: "100%" }} onKeyDown={e => { if (e.key === "Enter") doLogin(); }} /></div>
      </div>
      {loginError && <div style={{ color: "var(--red)", fontSize: 12, marginBottom: 10, fontWeight: 600 }}>{loginError}</div>}
      <button className="btn btn-p" style={{ width: "100%", padding: "10px", fontSize: 13, justifyContent: "center" }} onClick={doLogin}>Log In</button>
    </div></div></>
  );

  const P = (p) => hasPerm(currentUser, p);
  const navItems = [
    P("view_dashboard") && "dashboard", P("view_stats") && "stats", P("view_leads") && "leads",
    P("view_pipeline") && "pipeline", P("view_deals") && "deals", P("view_verification") && "verification",
    P("view_all_leads") && "clients", "tasks", "tracker", "transfers", P("view_payroll") && "payroll", P("view_users") && "users"
  ].filter(Boolean);

  const myLeads = P("view_all_leads") ? leads : leads.filter(l => l.assignedTo === currentUser.id && l.disposition !== "Transferred to Closer");
  const NAV_ICONS = { dashboard:"â—«", stats:"ðŸ“Š", leads:"âœï¸", pipeline:"ðŸ“ˆ", deals:"ðŸ“‹", verification:"âœ“", clients:"ðŸ’°", tasks:"â˜‘", tracker:"ðŸ“…", transfers:"â™»ï¸", payroll:"ðŸ’µ", chat:"ðŸ’¬", users:"ðŸ‘¥" };

  const handleDisposition = (leadId, dispo, closerId, callbackDt) => {
    const lead = leads.find(l => l.id === leadId);
    setLeads(prev => prev.map(l => {
      if (l.id !== leadId) return l;
      const u = { ...l, disposition: dispo };
      if (dispo === "Transferred to Closer" && closerId) { u.transferredTo = closerId; u.assignedTo = closerId; if (!l.originalFronter) u.originalFronter = l.assignedTo; }
      if (dispo === "Transferred to Verification") u.transferredTo = "verification";
      if (dispo === "Callback" && callbackDt) u.callbackDate = callbackDt;
      return u;
    }));
    if (dispo === "Transferred to Closer" && closerId && lead) {
      setTransferLog(p => [...p, { id: uid(), type: "fronter_to_closer", from: currentUser.id, to: closerId, leadId, leadName: lead.ownerName, timestamp: nowT() }]);
      setNotifications(p => [...p, { id: uid(), to: closerId, msg: `New lead transferred: ${lead.ownerName} (${lead.resort})`, from: currentUser.name, time: nowT(), read: false }]);
    }
    setSelectedLead(null);
  };

  const handleCSVImport = text => {
    const lines = text.trim().split("\n"); if (lines.length < 2) return;
    const nl = [];
    for (let i = 1; i < lines.length; i++) { const v = lines[i].split(",").map(s => s.trim()); if (v.length < 2) continue;
      nl.push({ id: uid(), resort: v[0]||"", ownerName: v[1]||"", phone1: v[2]||"", phone2: v[3]||"", city: v[4]||"", st: v[5]||"", zip: v[6]||"", resortLocation: v[7]||(v[4]&&v[5]?`${v[4]}, ${v[5]}`:""), assignedTo: null, originalFronter: null, disposition: null, transferredTo: null, source: "csv" });
    } setLeads(p => [...p, ...nl]); setModal(null);
  };

  const saveDeal = deal => {
    setDeals(prev => { const i = prev.findIndex(d => d.id === deal.id); if (i >= 0) return prev.map(d => d.id === deal.id ? deal : d); return [...prev, deal]; });
    // If new deal with assignedAdmin, log transfer and notify
    if (deal.assignedAdmin && !deals.find(d => d.id === deal.id)) {
      setTransferLog(p => [...p, { id: uid(), type: "closer_to_admin", from: currentUser.id, to: deal.assignedAdmin, dealId: deal.id, dealName: deal.ownerName, amount: deal.fee, timestamp: nowT() }]);
      setNotifications(p => [...p, { id: uid(), to: deal.assignedAdmin, type: "new_deal", dealId: deal.id, msg: `New deal submitted: ${deal.ownerName} - ${fmt$(deal.fee)}`, from: currentUser.name, time: nowT(), read: false }]);
    }
    setModal(null);
  };
  const sendMessage = (chatId, text, type = "text", meta = null) => { if (type === "text" && !text.trim()) return; setMessages(p => [...p, { id: uid(), chatId, userId: currentUser.id, text, time: nowT(), type, meta }]); };
  const createChat = chat => { setChats(p => [...p, { ...chat, id: uid(), createdBy: currentUser.id }]); setModal(null); };
  const addUser = user => { setUsers(p => [...p, { ...user, id: uid(), status: "offline" }]); setModal(null); };

  return (
    <><style>{CSS}{crmTheme !== "light" ? `:root{--bg-0:${THEMES[crmTheme].bg0};--bg-1:${THEMES[crmTheme].bg1};--bg-2:${THEMES[crmTheme].bg2};--bg-3:${THEMES[crmTheme].bg3};--bg-4:${THEMES[crmTheme].bg4};--border:${THEMES[crmTheme].border};--border-h:${THEMES[crmTheme].borderH};--t1:${THEMES[crmTheme].t1};--t2:${THEMES[crmTheme].t2};--t3:${THEMES[crmTheme].t3};--grad:${THEMES[crmTheme].grad}}.login-box h1{color:${THEMES[crmTheme].t1}}.btn-p{background:${THEMES[crmTheme].t1};border-color:${THEMES[crmTheme].t1}}.btn-p:hover{background:${THEMES[crmTheme].t2}}.send-btn{background:${THEMES[crmTheme].t1}}.send-btn:hover{background:${THEMES[crmTheme].t2}}.rail-logo{background:${THEMES[crmTheme].bg1}}.rail-logo img{${crmTheme==="dark"?"filter:invert(1)":""}}}` : ""}</style>
    <div className="shell">
      <div className="rail">
        <div className="rail-logo"><img src={PRIME_LOGO} alt="P" /></div>
        {navItems.map(n => <button key={n} className={`rail-btn ${view === n ? "on" : ""}`} onClick={() => setView(n)} title={n}>{NAV_ICONS[n]}</button>)}
        {currentUser.role === "master_admin" && <button className={`rail-btn ${view === "settings" ? "on" : ""}`} onClick={() => setView("settings")} title="CRM Settings">âš™ï¸</button>}
        <div className="rail-spacer" />
        <div className="rail-av" style={{ background: currentUser.color }} title="Log out" onClick={logout}>{currentUser.avatar}</div>
      </div>
      <div className="main">
        <div className="topbar">
          <h2 style={{ textTransform: "capitalize" }}>{view === "users" ? "User Management" : view === "stats" ? "Statistics" : view === "payroll" ? "Payroll" : view === "transfers" ? "Transfer Log" : view === "tasks" ? "Tasks" : view === "tracker" ? "Weekly Deal Tracker" : view === "clients" ? "Deals / Clients" : view === "settings" ? "CRM Settings" : view === "dashboard" ? crmName : view}</h2>
          <span className="topbar-role" style={{ background: ROLE_COLORS[currentUser.role] + "22", color: ROLE_COLORS[currentUser.role] }}>{ROLE_LABELS[currentUser.role]}</span>
          {notifications.filter(n => n.to === currentUser.id && !n.read).length > 0 && (
            <button className="btn btn-sm btn-d" onClick={() => { const n = notifications.find(x => x.to === currentUser.id && !x.read); if (n) { setNotifications(p => p.map(x => x.id === n.id ? { ...x, read: true } : x)); if (n.type === "new_deal" && n.dealId) { setSelectedDeal(n.dealId); setView("verification"); } } }}>
              ðŸ”” {notifications.filter(n => n.to === currentUser.id && !n.read).length} New
            </button>
          )}
          <span style={{ fontSize: 12, color: "var(--t3)" }}>{currentUser.name}</span>
        </div>
        <div className="content">
          {view === "dashboard" && <DashboardView leads={leads} deals={deals} users={users} tasks={tasks} currentUser={currentUser} crmName={crmName} />}
          {view === "stats" && <StatsView leads={leads} deals={deals} users={users} />}
          {view === "leads" && <LeadsView leads={P("view_all_leads") ? leads : myLeads} allLeads={leads} users={users} currentUser={currentUser} P={P} selected={selectedLead} onSelect={setSelectedLead} onDisposition={handleDisposition} onImport={() => setModal("csv_import")} onAssign={ids => setModal({ type: "assign", leadIds: ids })} onAddLead={() => setModal("add_lead")} bulkSelected={bulkSelected} setBulkSelected={setBulkSelected} onBulkAssign={userId => { setLeads(p => p.map(l => bulkSelected.includes(l.id) ? { ...l, assignedTo: userId, originalFronter: userId } : l)); setBulkSelected([]); }} onCreateDealFromLead={lead => setModal({ type: "deal_from_lead", lead })} />}
          {view === "pipeline" && <PipelineView leads={leads} deals={deals} users={users} currentUser={currentUser} P={P} onSelect={id => { setSelectedLead(id); setView("leads"); }} />}
          {view === "deals" && <DealsView deals={deals} users={users} currentUser={currentUser} P={P} selected={selectedDeal} onSelect={setSelectedDeal} onNew={() => setModal("new_deal")} onEdit={d => setModal({ type: "edit_deal", deal: d })} dealStatuses={dealStatuses} />}
          {view === "verification" && <VerificationView deals={deals} users={users} setDeals={setDeals} P={P} currentUser={currentUser} dealStatuses={dealStatuses} />}
          {view === "clients" && <ClientsView deals={deals} leads={leads} users={users} currentUser={currentUser} setDeals={setDeals} onEdit={d => setModal({ type: "edit_deal", deal: d })} />}
          {view === "tasks" && <TasksView tasks={tasks} setTasks={setTasks} users={users} currentUser={currentUser} P={P} deals={deals} leads={leads} />}
          {view === "tracker" && <TrackerView deals={deals} users={users} currentUser={currentUser} />}
          {view === "transfers" && <TransfersView transferLog={transferLog} deals={deals} leads={leads} users={users} currentUser={currentUser} setLeads={setLeads} setDeals={setDeals} onEditDeal={d => setModal({ type: "edit_deal", deal: d })} />}
          {view === "payroll" && <PayrollView deals={deals} users={users} currentUser={currentUser} crmName={crmName} />}
          {view === "users" && <UsersView users={users} setUsers={setUsers} currentUser={currentUser} P={P} onAdd={() => setModal("add_user")} onEditPerms={u => setModal({ type: "edit_perms", user: u })} />}
          {view === "settings" && currentUser.role === "master_admin" && <SettingsView crmName={crmName} setCrmName={setCrmName} dealStatuses={dealStatuses} setDealStatuses={setDealStatuses} crmTheme={crmTheme} setCrmTheme={setCrmTheme} themes={THEMES} />}
        </div>
      </div>
    </div>
    {modal === "csv_import" && <CSVImportModal onClose={() => setModal(null)} onImport={handleCSVImport} />}
    {modal === "add_lead" && <AddLeadModal onClose={() => setModal(null)} onSave={l => { setLeads(p => [...p, { ...l, id: uid(), assignedTo: null, originalFronter: null, disposition: null, transferredTo: null, source: "manual" }]); setModal(null); }} />}
    {modal?.type === "assign" && <AssignModal users={users} onClose={() => setModal(null)} onAssign={userId => { setLeads(p => p.map(l => modal.leadIds.includes(l.id) ? { ...l, assignedTo: userId, originalFronter: userId } : l)); setModal(null); }} />}
    {(modal === "new_deal" || modal?.type === "edit_deal") && <DealFormModal deal={modal?.deal || null} users={users} currentUser={currentUser} onClose={() => setModal(null)} onSave={saveDeal} />}
    {modal?.type === "deal_from_lead" && <DealFormModal deal={{ id: uid(), timestamp: todayStr(), chargedDate: "", wasVD: "", fronter: modal.lead.originalFronter || "", closer: currentUser.id, fee: "", ownerName: modal.lead.ownerName, mailingAddress: "", cityStateZip: modal.lead.city + ", " + modal.lead.st + " " + modal.lead.zip, primaryPhone: modal.lead.phone1, secondaryPhone: modal.lead.phone2, email: "", weeks: "", askingRental: "", resortName: modal.lead.resort, resortCityState: modal.lead.resortLocation, exchangeGroup: "", bedBath: "", usage: "", askingSalePrice: "", nameOnCard: "", cardType: "", bank: "", cardNumber: "", expDate: "", cv2: "", billingAddress: "", bank2: "", cardNumber2: "", expDate2: "", cv2_2: "", usingTimeshare: "", lookingToGetOut: "", verificationNum: "", notes: "", loginInfo: "", correspondence: [], files: [], snr: "", login: "", merchant: "", appLogin: "", assignedAdmin: "", status: "pending_admin", charged: "no", chargedBack: "no" }} users={users} currentUser={currentUser} onClose={() => setModal(null)} onSave={saveDeal} />}
    {modal === "new_chat" && <NewChatModal users={users} currentUser={currentUser} onClose={() => setModal(null)} onCreate={createChat} />}
    {modal === "add_user" && <AddUserModal onClose={() => setModal(null)} onSave={addUser} />}
    {modal?.type === "edit_perms" && <EditPermsModal user={modal.user} users={users} setUsers={setUsers} onClose={() => setModal(null)} />}

    {/* Floating Chat Button */}
    {P("view_chat") && !chatOpen && (() => {
      const lastSeenIdx = lastSeenMsgId.current ? messages.findIndex(m => m.id === lastSeenMsgId.current) : -1;
      const unread = lastSeenIdx >= 0 ? messages.slice(lastSeenIdx + 1).filter(m => m.userId !== currentUser.id) : messages.filter(m => m.userId !== currentUser.id);
      const hasNewMessages = unread.length > 0;
      const hasMention = unread.some(m => m.text && currentUser?.name && m.text.includes("@" + currentUser.name));
      return (
        <div onClick={() => { setChatOpen(true); setChatMinimized(false); }} style={{ position: "fixed", bottom: 24, right: 24, width: 56, height: 56, borderRadius: "50%", background: "#111", color: "#fff", display: "flex", alignItems: "center", justifyContent: "center", fontSize: 24, cursor: "pointer", boxShadow: "0 4px 20px rgba(0,0,0,.3)", zIndex: 9999, transition: "transform .2s" }} onMouseEnter={e => e.currentTarget.style.transform = "scale(1.1)"} onMouseLeave={e => e.currentTarget.style.transform = "scale(1)"}>
          ðŸ’¬
          {hasMention && <div style={{ position: "absolute", top: -2, right: -2, width: 18, height: 18, borderRadius: "50%", background: "#2563eb", border: "2px solid #111", animation: "pulse 1.5s infinite" }} />}
          {hasNewMessages && !hasMention && <div style={{ position: "absolute", top: -2, right: -2, width: 14, height: 14, borderRadius: "50%", background: "#ef4444", border: "2px solid #111" }} />}
        </div>
      );
    })()}

    {/* Floating Chat Popup â€” draggable & resizable */}
    {P("view_chat") && chatOpen && (
      <div data-chat-popup="1" style={{ position: "fixed", left: chatPos.x != null ? chatPos.x : undefined, top: chatPos.y != null ? chatPos.y : undefined, bottom: chatPos.y == null ? (chatMinimized ? 24 : 24) : undefined, right: chatPos.x == null ? 24 : undefined, width: chatMinimized ? 280 : chatSize.w, height: chatMinimized ? 48 : chatSize.h, borderRadius: chatMinimized ? 24 : 12, background: "var(--bg-0)", border: "1px solid var(--border)", boxShadow: "0 8px 40px rgba(0,0,0,.25)", zIndex: 9999, display: "flex", flexDirection: "column", overflow: "hidden", transition: chatDrag.current || chatResize.current ? "none" : "width .25s, height .25s, border-radius .25s" }}>
        {/* Resize handle â€” top-left corner */}
        {!chatMinimized && <div onMouseDown={onResizeStart} style={{ position: "absolute", top: 0, left: 0, width: 18, height: 18, cursor: "nw-resize", zIndex: 10 }}><svg width="18" height="18" viewBox="0 0 18 18" style={{ opacity: 0.3 }}><line x1="4" y1="14" x2="14" y2="4" stroke="var(--t3)" strokeWidth="1.5"/><line x1="4" y1="9" x2="9" y2="4" stroke="var(--t3)" strokeWidth="1.5"/></svg></div>}
        {/* Chat header â€” drag handle */}
        <div onMouseDown={onDragStart} style={{ display: "flex", alignItems: "center", justifyContent: "space-between", padding: chatMinimized ? "10px 16px" : "12px 16px", background: "#111", color: "#fff", cursor: chatMinimized ? "pointer" : "grab", flexShrink: 0, userSelect: "none" }} onClick={() => chatMinimized && setChatMinimized(false)}>
          <div style={{ display: "flex", alignItems: "center", gap: 8 }}>
            <span style={{ fontSize: 16 }}>ðŸ’¬</span>
            <span style={{ fontSize: 13, fontWeight: 600 }}>Messages</span>
            {chatMinimized && <span style={{ fontSize: 11, color: "rgba(255,255,255,.6)" }}>(click to expand)</span>}
            {!chatMinimized && <span style={{ fontSize: 9, color: "rgba(255,255,255,.35)" }}>drag to move</span>}
          </div>
          <div style={{ display: "flex", gap: 6 }}>
            <div onClick={e => { e.stopPropagation(); setChatMinimized(!chatMinimized); }} style={{ width: 24, height: 24, borderRadius: 4, display: "flex", alignItems: "center", justifyContent: "center", cursor: "pointer", fontSize: 14, color: "rgba(255,255,255,.7)" }} title={chatMinimized ? "Expand" : "Minimize"}>{chatMinimized ? "â–¡" : "â€“"}</div>
            <div onClick={e => { e.stopPropagation(); setChatOpen(false); setChatPos({ x: null, y: null }); }} style={{ width: 24, height: 24, borderRadius: 4, display: "flex", alignItems: "center", justifyContent: "center", cursor: "pointer", fontSize: 14, color: "rgba(255,255,255,.7)" }} title="Close">âœ•</div>
          </div>
        </div>
        {/* Chat body */}
        {!chatMinimized && (
          <div style={{ flex: 1, display: "flex", overflow: "hidden" }}>
            <ChatViewFull chats={chats.filter(c => c.members.includes(currentUser.id))} messages={messages} users={users} currentUser={currentUser} P={P} selectedChat={selectedChat} onSelectChat={setSelectedChat} onSend={sendMessage} onNewChat={() => setModal("new_chat")} />
          </div>
        )}
        {/* Resize handle â€” bottom-right corner */}
        {!chatMinimized && <div onMouseDown={e => { e.stopPropagation(); e.preventDefault(); chatResize.current = { startX: e.clientX, startY: e.clientY, origW: chatSize.w, origH: chatSize.h }; const onMove = ev => { if (!chatResize.current) return; setChatSize({ w: Math.max(320, Math.min(800, chatResize.current.origW + (ev.clientX - chatResize.current.startX))), h: Math.max(300, Math.min(900, chatResize.current.origH + (ev.clientY - chatResize.current.startY))) }); }; const onUp = () => { chatResize.current = null; window.removeEventListener("mousemove", onMove); window.removeEventListener("mouseup", onUp); }; window.addEventListener("mousemove", onMove); window.addEventListener("mouseup", onUp); }} style={{ position: "absolute", bottom: 0, right: 0, width: 18, height: 18, cursor: "se-resize", zIndex: 10 }}><svg width="18" height="18" viewBox="0 0 18 18" style={{ opacity: 0.3 }}><line x1="14" y1="4" x2="4" y2="14" stroke="var(--t3)" strokeWidth="1.5"/><line x1="14" y1="9" x2="9" y2="14" stroke="var(--t3)" strokeWidth="1.5"/></svg></div>}
      </div>
    )}
    </>
  );
}

// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
// STATISTICS â€” with Weekly/Monthly/Quarterly/Yearly
// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
function StatsView({ leads, deals, users }) {
  const [tab, setTab] = useState("revenue");
  const fronters = users.filter(u => u.role === "fronter");
  const closers = users.filter(u => u.role === "closer");
  const chargedDeals = deals.filter(d => d.charged === "yes");
  const chargedBackDeals = deals.filter(d => d.chargedBack === "yes");
  const totalRev = deals.reduce((s, d) => s + (Number(d.fee) || 0), 0);
  const chargedRev = chargedDeals.reduce((s, d) => s + (Number(d.fee) || 0), 0);
  const cbRev = chargedBackDeals.reduce((s, d) => s + (Number(d.fee) || 0), 0);

  // Period calculations
  const now = new Date();
  const weekAgo = new Date(now); weekAgo.setDate(now.getDate() - 7);
  const monthAgo = new Date(now); monthAgo.setMonth(now.getMonth() - 1);
  const quarterAgo = new Date(now); quarterAgo.setMonth(now.getMonth() - 3);
  const yearAgo = new Date(now); yearAgo.setFullYear(now.getFullYear() - 1);

  const inPeriod = (d, from) => { const dt = new Date(d.chargedDate || d.timestamp); return dt >= from && dt <= now; };
  const periodDeals = (from) => chargedDeals.filter(d => inPeriod(d, from));
  const periodRev = (from) => periodDeals(from).reduce((s, d) => s + (Number(d.fee) || 0), 0);
  const periodCB = (from) => chargedBackDeals.filter(d => inPeriod(d, from));
  const periodCBRev = (from) => periodCB(from).reduce((s, d) => s + (Number(d.fee) || 0), 0);

  const Metric = ({ label, value, color, sub }) => (
    <div className="icard"><div className="lbl">{label}</div><div className="val xl" style={{ color }}>{value}</div>{sub && <div style={{ fontSize: 10, color: "var(--t3)", marginTop: 4 }}>{sub}</div>}</div>
  );
  const BarPct = ({ value, max, color }) => (
    <div className="stat-bar"><div className="stat-fill" style={{ width: max > 0 ? (value / max * 100) + "%" : "0%", background: color }} /></div>
  );

  // Fronter stats
  const fronterStats = fronters.map(f => {
    const total = leads.filter(l => l.originalFronter === f.id).length;
    const transferred = leads.filter(l => l.originalFronter === f.id && l.disposition === "Transferred to Closer").length;
    const fd = deals.filter(d => d.fronter === f.id);
    const fCharged = fd.filter(d => d.charged === "yes").length;
    const fCB = fd.filter(d => d.chargedBack === "yes").length;
    return { ...f, total, transferred, deals: fd.length, fCharged, fCB, pct: total > 0 ? (transferred / total * 100).toFixed(1) : "0.0" };
  });

  const closerStats = closers.map(c => {
    const received = leads.filter(l => l.transferredTo === c.id).length;
    const self = leads.filter(l => l.assignedTo === c.id && !l.originalFronter).length;
    const totalL = received + self;
    const cd = deals.filter(d => d.closer === c.id);
    const charged = cd.filter(d => d.charged === "yes").length;
    const cb = cd.filter(d => d.chargedBack === "yes").length;
    const rev = cd.reduce((s, d) => s + (Number(d.fee) || 0), 0);
    return { ...c, received, self, totalL, deals: cd.length, charged, cb, rev, closePct: totalL > 0 ? (cd.length / totalL * 100).toFixed(1) : "0.0", chargedPct: cd.length > 0 ? (charged / cd.length * 100).toFixed(1) : "0.0", cbPct: cd.length > 0 ? (cb / cd.length * 100).toFixed(1) : "0.0" };
  });

  return (
    <div style={{ flex: 1, display: "flex", flexDirection: "column", overflow: "hidden" }}>
      <div className="tabs">
        {[["revenue","Revenue Periods"],["fronters","Fronter Stats"],["closers","Closer Stats"],["deals","All Deals"]].map(([k,l])=><div key={k} className={`tab ${tab===k?"on":""}`} onClick={()=>setTab(k)}>{l}</div>)}
      </div>
      <div style={{ flex: 1, overflowY: "auto", padding: 20 }}>

        {tab === "revenue" && (
          <div className="fin">
            <div className="sec-title" style={{ marginTop: 0 }}>Charged Revenue by Period</div>
            <div className="igrid c4" style={{ marginBottom: 24 }}>
              {[["This Week", weekAgo], ["This Month", monthAgo], ["This Quarter", quarterAgo], ["This Year", yearAgo]].map(([label, from], i) => (
                <div key={i} className="period-card">
                  <div className="l">{label}</div>
                  <div className="v" style={{ color: "var(--green)" }}>{fmt$(periodRev(from))}</div>
                  <div className="ct">{periodDeals(from).length} deals charged</div>
                </div>
              ))}
            </div>
            <div className="sec-title">Chargebacks by Period</div>
            <div className="igrid c4" style={{ marginBottom: 24 }}>
              {[["This Week", weekAgo], ["This Month", monthAgo], ["This Quarter", quarterAgo], ["This Year", yearAgo]].map(([label, from], i) => (
                <div key={i} className="period-card">
                  <div className="l">{label}</div>
                  <div className="v" style={{ color: "var(--red)" }}>{fmt$(periodCBRev(from))}</div>
                  <div className="ct">{periodCB(from).length} chargebacks</div>
                </div>
              ))}
            </div>
            <div className="sec-title">Net Revenue by Period</div>
            <div className="igrid c4" style={{ marginBottom: 24 }}>
              {[["This Week", weekAgo], ["This Month", monthAgo], ["This Quarter", quarterAgo], ["This Year", yearAgo]].map(([label, from], i) => {
                const net = periodRev(from) - periodCBRev(from);
                return (
                  <div key={i} className="period-card">
                    <div className="l">{label}</div>
                    <div className="v" style={{ color: net >= 0 ? "var(--blue)" : "var(--red)" }}>{fmt$(net)}</div>
                    <div className="ct">Net after chargebacks</div>
                  </div>
                );
              })}
            </div>
            <div className="igrid">
              <Metric label="All-Time Charged" value={fmt$(chargedRev)} color="var(--green)" sub={`${chargedDeals.length} deals`} />
              <Metric label="All-Time Chargebacks" value={fmt$(cbRev)} color="var(--red)" sub={`${chargedBackDeals.length} deals`} />
            </div>
          </div>
        )}

        {tab === "fronters" && (
          <div className="fin">
            {fronterStats.map(f => (
              <div key={f.id} className="icard" style={{ marginBottom: 14 }}>
                <div style={{ display: "flex", alignItems: "center", gap: 12, marginBottom: 12 }}>
                  <div style={{ width: 36, height: 36, borderRadius: "50%", background: f.color, display: "flex", alignItems: "center", justifyContent: "center", fontSize: 12, fontWeight: 600, color: "#fff" }}>{f.avatar}</div>
                  <div style={{ flex: 1 }}><div style={{ fontSize: 15, fontWeight: 700 }}>{f.name}</div><div style={{ fontSize: 11, color: "var(--t3)" }}>Fronter</div></div>
                  <div style={{ textAlign: "right" }}><div style={{ fontSize: 24, fontWeight: 800, fontFamily: "'JetBrains Mono',monospace", color: "var(--pink)" }}>{f.pct}%</div><div style={{ fontSize: 10, color: "var(--t3)" }}>FRONTING RATE</div></div>
                </div>
                <div className="igrid c4" style={{ marginBottom: 0 }}>
                  <div style={{ textAlign: "center" }}><div style={{ fontSize: 18, fontWeight: 700, fontFamily: "'JetBrains Mono',monospace" }}>{f.total}</div><div style={{ fontSize: 9, color: "var(--t3)", textTransform: "uppercase" }}>Leads</div></div>
                  <div style={{ textAlign: "center" }}><div style={{ fontSize: 18, fontWeight: 700, fontFamily: "'JetBrains Mono',monospace", color: "var(--pink)" }}>{f.transferred}</div><div style={{ fontSize: 9, color: "var(--t3)", textTransform: "uppercase" }}>To Closer</div></div>
                  <div style={{ textAlign: "center" }}><div style={{ fontSize: 18, fontWeight: 700, fontFamily: "'JetBrains Mono',monospace", color: "var(--green)" }}>{f.fCharged}</div><div style={{ fontSize: 9, color: "var(--t3)", textTransform: "uppercase" }}>Charged</div></div>
                  <div style={{ textAlign: "center" }}><div style={{ fontSize: 18, fontWeight: 700, fontFamily: "'JetBrains Mono',monospace", color: "var(--red)" }}>{f.fCB}</div><div style={{ fontSize: 9, color: "var(--t3)", textTransform: "uppercase" }}>Chargebacks</div></div>
                </div>
              </div>
            ))}
          </div>
        )}

        {tab === "closers" && (
          <div className="fin">
            {closerStats.map(c => (
              <div key={c.id} className="icard" style={{ marginBottom: 14 }}>
                <div style={{ display: "flex", alignItems: "center", gap: 12, marginBottom: 12 }}>
                  <div style={{ width: 36, height: 36, borderRadius: "50%", background: c.color, display: "flex", alignItems: "center", justifyContent: "center", fontSize: 12, fontWeight: 600, color: "#fff" }}>{c.avatar}</div>
                  <div style={{ flex: 1 }}><div style={{ fontSize: 15, fontWeight: 700 }}>{c.name}</div><div style={{ fontSize: 11, color: "var(--t3)" }}>Closer</div></div>
                  <div style={{ display: "flex", gap: 16, textAlign: "right" }}>
                    <div><div style={{ fontSize: 22, fontWeight: 800, fontFamily: "'JetBrains Mono',monospace", color: "var(--purple)" }}>{c.closePct}%</div><div style={{ fontSize: 9, color: "var(--t3)" }}>CLOSE RATE</div></div>
                    <div><div style={{ fontSize: 22, fontWeight: 800, fontFamily: "'JetBrains Mono',monospace", color: "var(--green)" }}>{c.chargedPct}%</div><div style={{ fontSize: 9, color: "var(--t3)" }}>CHARGED</div></div>
                  </div>
                </div>
                <div style={{ display: "grid", gridTemplateColumns: "repeat(6,1fr)", gap: 8, marginBottom: 0 }}>
                  {[["From Fronters",c.received,""],["Self-Sourced",c.self,""],["Deals",c.deals,"var(--blue)"],["Charged",c.charged,"var(--green)"],["Chargebacks",c.cb,"var(--red)"],["Revenue",fmt$(c.rev),"var(--green)"]].map(([l,v,col],i)=>(
                    <div key={i} style={{ textAlign: "center" }}><div style={{ fontSize: 16, fontWeight: 700, fontFamily: "'JetBrains Mono',monospace", color: col || "var(--t1)" }}>{v}</div><div style={{ fontSize: 9, color: "var(--t3)", textTransform: "uppercase" }}>{l}</div></div>
                  ))}
                </div>
                <div style={{ marginTop: 8, fontSize: 11, display: "flex", justifyContent: "space-between" }}><span>Chargeback Rate</span><span style={{ fontFamily: "'JetBrains Mono',monospace", fontWeight: 700, color: Number(c.cbPct) > 20 ? "var(--red)" : "var(--t2)" }}>{c.cbPct}%</span></div>
                <BarPct value={Number(c.cbPct)} max={100} color={Number(c.cbPct) > 20 ? "var(--red)" : "var(--amber)"} />
              </div>
            ))}
          </div>
        )}

        {tab === "deals" && (
          <div className="fin">
            <div className="igrid c4" style={{ marginBottom: 20 }}>
              <Metric label="Total Deals" value={deals.length} color="var(--blue)" />
              <Metric label="Charged" value={chargedDeals.length} color="var(--green)" sub={fmt$(chargedRev)} />
              <Metric label="Chargebacks" value={chargedBackDeals.length} color="var(--red)" sub={fmt$(cbRev)} />
              <Metric label="Cancelled" value={deals.filter(d => d.status === "cancelled").length} color="var(--t3)" sub="Deals cancelled" />
            </div>
            <div className="tbl-wrap"><table className="tbl"><thead><tr><th>Owner</th><th>Resort</th><th>Fee</th><th>Fronter</th><th>Closer</th><th>Admin</th><th>Status</th><th>Charged</th><th>Charged Date</th><th>CB</th><th>Deal Date</th></tr></thead>
              <tbody>{deals.map(d => {
                const status = d.status === "cancelled" ? "Cancelled" : d.chargedBack === "yes" ? "Chargeback" : d.charged === "yes" ? "Charged" : d.status === "pending_admin" ? "Pending Admin" : d.status === "in_verification" ? "In Verification" : d.status || "Pending";
                const statusCol = d.status === "cancelled" ? "var(--t3)" : d.chargedBack === "yes" ? "var(--red)" : d.charged === "yes" ? "var(--green)" : "var(--amber)";
                return (
                <tr key={d.id}>
                  <td style={{ fontWeight: 600 }}>{d.ownerName}</td>
                  <td style={{ fontSize: 11 }}>{d.resortName}</td>
                  <td style={{ fontFamily: "'JetBrains Mono',monospace", fontWeight: 600, color: "var(--green)" }}>{fmt$(d.fee)}</td>
                  <td style={{ fontSize: 11 }}>{users.find(u => u.id === d.fronter)?.name || "Self"}</td>
                  <td style={{ fontSize: 11 }}>{users.find(u => u.id === d.closer)?.name || "-"}</td>
                  <td style={{ fontSize: 11 }}>{users.find(u => u.id === d.assignedAdmin)?.name || "-"}</td>
                  <td><span className="tag" style={{ background: statusCol + "22", color: statusCol }}>{status}</span></td>
                  <td><span className="tag" style={{ background: d.charged === "yes" ? "var(--green-s)" : "var(--amber-s)", color: d.charged === "yes" ? "var(--green)" : "var(--amber)" }}>{d.charged === "yes" ? "YES" : "NO"}</span></td>
                  <td style={{ color: "var(--t3)", fontSize: 11, fontFamily: "'JetBrains Mono',monospace" }}>{d.chargedDate || "-"}</td>
                  <td><span className="tag" style={{ background: d.chargedBack === "yes" ? "var(--red-s)" : "var(--bg-3)", color: d.chargedBack === "yes" ? "var(--red)" : "var(--t3)" }}>{d.chargedBack === "yes" ? "YES" : "NO"}</span></td>
                  <td style={{ color: "var(--t3)", fontSize: 11, fontFamily: "'JetBrains Mono',monospace" }}>{d.timestamp}</td>
                </tr>);
              })}</tbody>
            </table></div>
          </div>
        )}
      </div>
    </div>
  );
}

// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
// DASHBOARD
// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
function DashboardView({ leads, deals, users, tasks, currentUser, crmName }) {
  const isCloser = currentUser.role === "closer";
  const charged = deals.filter(d => d.charged === "yes" && d.chargedBack !== "yes");
  const cbacks = deals.filter(d => d.chargedBack === "yes");
  const pending = deals.filter(d => d.charged !== "yes" && d.status !== "cancelled");
  const cancelled = deals.filter(d => d.status === "cancelled");
  const totalRev = charged.reduce((s, d) => s + (Number(d.fee) || 0), 0);
  const cbRev = cbacks.reduce((s, d) => s + (Number(d.fee) || 0), 0);
  const pendRev = pending.reduce((s, d) => s + (Number(d.fee) || 0), 0);
  const myTasks = tasks ? tasks.filter(t => t.assignedTo === currentUser?.id && t.status === "open") : [];
  const allOpenTasks = tasks || [];

  // Weekly calculation (Mon-Sun)
  const now = new Date();
  const dayOfWeek = now.getDay();
  const weekStart = new Date(now); weekStart.setDate(now.getDate() - ((dayOfWeek + 6) % 7)); weekStart.setHours(0,0,0,0);
  const isThisWeek = (dateStr) => { if (!dateStr) return false; const d = new Date(dateStr); return d >= weekStart; };
  const weekDeals = deals.filter(d => isThisWeek(d.timestamp));
  const weekCharged = weekDeals.filter(d => d.charged === "yes" && d.chargedBack !== "yes");
  const weekCB = weekDeals.filter(d => d.chargedBack === "yes");
  const weekRev = weekCharged.reduce((s, d) => s + (Number(d.fee) || 0), 0);
  const weekLabel = weekStart.toLocaleDateString("en-US", { month: "short", day: "numeric" }) + " - " + now.toLocaleDateString("en-US", { month: "short", day: "numeric" });

  // Monthly data for bar chart (last 6 months)
  const months = [];
  for (let i = 5; i >= 0; i--) { const d = new Date(); d.setMonth(d.getMonth() - i); months.push({ month: d.toLocaleDateString("en-US", { month: "short" }), year: d.getFullYear(), m: d.getMonth(), y: d.getFullYear() }); }
  const monthlyData = months.map(m => {
    const mDeals = deals.filter(d => { const dt = new Date(d.timestamp); return dt.getMonth() === m.m && dt.getFullYear() === m.y; });
    const mCharged = mDeals.filter(d => d.charged === "yes" && d.chargedBack !== "yes");
    const mCB = mDeals.filter(d => d.chargedBack === "yes");
    return { label: m.month, deals: mDeals.length, charged: mCharged.reduce((s, d) => s + (Number(d.fee) || 0), 0), cb: mCB.reduce((s, d) => s + (Number(d.fee) || 0), 0) };
  });
  const maxBar = Math.max(...monthlyData.map(m => m.charged), 1);

  // Donut chart helper
  const Donut = ({ data, size = 140 }) => {
    const total = data.reduce((s, d) => s + d.value, 0) || 1;
    let cumulative = 0;
    const r = size / 2 - 10, cx = size / 2, cy = size / 2;
    return (
      <svg width={size} height={size} viewBox={`0 0 ${size} ${size}`}>
        {data.map((d, i) => {
          const start = cumulative / total * 360;
          cumulative += d.value;
          const end = cumulative / total * 360;
          const largeArc = end - start > 180 ? 1 : 0;
          const sr = (Math.PI / 180) * (start - 90), er = (Math.PI / 180) * (end - 90);
          const x1 = cx + r * Math.cos(sr), y1 = cy + r * Math.sin(sr);
          const x2 = cx + r * Math.cos(er), y2 = cy + r * Math.sin(er);
          const ir = r * 0.6;
          const x3 = cx + ir * Math.cos(er), y3 = cy + ir * Math.sin(er);
          const x4 = cx + ir * Math.cos(sr), y4 = cy + ir * Math.sin(sr);
          return <path key={i} d={`M${x1},${y1} A${r},${r} 0 ${largeArc} 1 ${x2},${y2} L${x3},${y3} A${ir},${ir} 0 ${largeArc} 0 ${x4},${y4} Z`} fill={d.color} />;
        })}
        <text x={cx} y={cy - 4} textAnchor="middle" fontSize="18" fontWeight="700" fill="var(--t1)" fontFamily="'JetBrains Mono',monospace">{total}</text>
        <text x={cx} y={cy + 12} textAnchor="middle" fontSize="9" fill="var(--t3)" fontFamily="Arial">total</text>
      </svg>
    );
  };

  // Line chart for weekly trend
  const weeklyData = [];
  for (let i = 7; i >= 0; i--) { const d = new Date(); d.setDate(d.getDate() - i * 7); const wEnd = new Date(d); wEnd.setDate(d.getDate() + 7); const wDeals = cbacks.filter(dd => { const dt = new Date(dd.timestamp); return dt >= d && dt < wEnd; }); weeklyData.push({ label: d.toLocaleDateString("en-US", { month: "short", day: "numeric" }), value: wDeals.reduce((s, dd) => s + (Number(dd.fee) || 0), 0) }); }
  const maxLine = Math.max(...weeklyData.map(w => w.value), 1);

  const prioColor = p => ({ high: "var(--red)", medium: "var(--amber)", low: "var(--green)" }[p] || "var(--t3)");

  return (
    <div style={{ flex: 1, overflowY: "auto", padding: 20 }}>
      <div className="fin" style={{ marginBottom: 20 }}><h2 style={{ fontSize: 20, fontWeight: 700 }}>Dashboard</h2><p style={{ fontSize: 12, color: "var(--t3)", marginTop: 2 }}>{crmName}</p></div>

      {/* KPI Cards */}
      <div className="igrid c4" style={{ marginBottom: 20 }}>
        {[!isCloser && { l: "Total Leads", v: leads.length, c: "#3b82f6", s: leads.filter(l => l.assignedTo).length + " assigned" }, isCloser && { l: "My Deals (Week)", v: weekDeals.filter(d => d.closer === currentUser.id).length, c: "#3b82f6", s: weekCharged.filter(d => d.closer === currentUser.id).length + " charged this week" },{ l: "Deals This Week", v: weekDeals.length, c: "#8b5cf6", s: weekCharged.length + " charged | " + weekCB.length + " CB | " + weekLabel },{ l: "Charged Revenue (Week)", v: fmt$(weekRev), c: "#10b981", s: weekCharged.length + " deals | All-time: " + fmt$(totalRev) },{ l: "Open Tasks", v: myTasks.length, c: "#f59e0b", s: allOpenTasks.filter(t => t.status === "open").length + " total open" }].filter(Boolean).map((m, i) => (
          <div key={i} className="icard fin" style={{ borderTop: "3px solid " + m.c }}><div className="lbl">{m.l}</div><div className="val xl" style={{ color: m.c }}>{m.v}</div><div style={{ fontSize: 10, color: "var(--t3)", marginTop: 4 }}>{m.s}</div></div>
        ))}
      </div>

      {/* Charts Row */}
      <div style={{ display: "grid", gridTemplateColumns: isCloser ? "1fr 1fr" : "1fr 1fr 1fr", gap: 16, marginBottom: 20 }}>

        {/* Donut - Deal Status */}
        <div className="icard fin">
          <div style={{ fontSize: 13, fontWeight: 600, marginBottom: 12 }}>Deal Status Breakdown</div>
          <div style={{ display: "flex", alignItems: "center", gap: 16 }}>
            <Donut data={[
              { value: charged.length, color: "#10b981" },
              { value: pending.length, color: "#f59e0b" },
              { value: cbacks.length, color: "#ef4444" },
              { value: cancelled.length, color: "#9ca3af" },
            ]} />
            <div style={{ fontSize: 11 }}>
              {[["Charged", charged.length, "#10b981"], ["Pending", pending.length, "#f59e0b"], ["Chargebacks", cbacks.length, "#ef4444"], ["Cancelled", cancelled.length, "#9ca3af"]].map(([l, v, c], i) => (
                <div key={i} style={{ display: "flex", alignItems: "center", gap: 8, marginBottom: 6 }}>
                  <div style={{ width: 10, height: 10, borderRadius: 2, background: c, flexShrink: 0 }} />
                  <span style={{ flex: 1 }}>{l}</span>
                  <span style={{ fontFamily: "'JetBrains Mono',monospace", fontWeight: 600 }}>{v}</span>
                  <span style={{ color: "var(--t3)", fontSize: 10 }}>({deals.length ? (v / deals.length * 100).toFixed(0) : 0}%)</span>
                </div>
              ))}
            </div>
          </div>
        </div>

        {/* Bar Chart - Monthly Revenue */}
        <div className="icard fin">
          <div style={{ fontSize: 13, fontWeight: 600, marginBottom: 12 }}>Monthly Sales Revenue</div>
          <div style={{ display: "flex", alignItems: "flex-end", gap: 6, height: 130 }}>
            {monthlyData.map((m, i) => (
              <div key={i} style={{ flex: 1, display: "flex", flexDirection: "column", alignItems: "center" }}>
                <div style={{ fontSize: 9, fontFamily: "'JetBrains Mono',monospace", color: "var(--t3)", marginBottom: 4 }}>{m.charged > 0 ? "$" + (m.charged / 1000).toFixed(1) + "k" : ""}</div>
                <div style={{ width: "100%", background: "#10b981", borderRadius: "3px 3px 0 0", minHeight: 2, height: Math.max(2, (m.charged / maxBar) * 100) + "px", transition: "height .3s" }} />
                <div style={{ fontSize: 9, color: "var(--t3)", marginTop: 4 }}>{m.label}</div>
              </div>
            ))}
          </div>
          <div style={{ display: "flex", gap: 12, marginTop: 10, fontSize: 10, color: "var(--t3)" }}>
            <span>Total: <strong style={{ color: "var(--green)" }}>{fmt$(monthlyData.reduce((s, m) => s + m.charged, 0))}</strong></span>
            <span>Avg: <strong>{fmt$(monthlyData.reduce((s, m) => s + m.charged, 0) / (monthlyData.filter(m => m.charged > 0).length || 1))}</strong>/mo</span>
          </div>
        </div>

        {/* Line Chart - Chargeback Trend (hidden for closers) */}
        {!isCloser && <div className="icard fin">
          <div style={{ fontSize: 13, fontWeight: 600, marginBottom: 12 }}>Chargeback Trend (8 Weeks)</div>
          <svg width="100%" height="130" viewBox="0 0 300 130" preserveAspectRatio="none">
            <line x1="0" y1="120" x2="300" y2="120" stroke="var(--border)" strokeWidth="1" />
            <line x1="0" y1="60" x2="300" y2="60" stroke="var(--border)" strokeWidth="0.5" strokeDasharray="4" />
            {weeklyData.length > 1 && (
              <polyline fill="none" stroke="#ef4444" strokeWidth="2.5" strokeLinejoin="round" strokeLinecap="round"
                points={weeklyData.map((w, i) => `${(i / (weeklyData.length - 1)) * 280 + 10},${120 - (w.value / maxLine) * 100}`).join(" ")} />
            )}
            {weeklyData.map((w, i) => (
              <g key={i}>
                <circle cx={(i / (weeklyData.length - 1)) * 280 + 10} cy={120 - (w.value / maxLine) * 100} r="4" fill="#ef4444" stroke="#fff" strokeWidth="1.5" />
                <text x={(i / (weeklyData.length - 1)) * 280 + 10} y="132" textAnchor="middle" fontSize="7" fill="var(--t3)" fontFamily="Arial">{w.label}</text>
              </g>
            ))}
          </svg>
          <div style={{ fontSize: 10, color: "var(--t3)", marginTop: 8 }}>Total CB: <strong style={{ color: "var(--red)" }}>{fmt$(cbRev)}</strong> from {cbacks.length} deals</div>
        </div>}
      </div>

      {/* Revenue Donut + Tasks Row */}
      <div style={{ display: "grid", gridTemplateColumns: isCloser ? "1fr" : "1fr 2fr", gap: 16, marginBottom: 20 }}>

        {/* Revenue Donut (hidden for closers) */}
        {!isCloser && <div className="icard fin">
          <div style={{ fontSize: 13, fontWeight: 600, marginBottom: 12 }}>Revenue Split</div>
          <div style={{ display: "flex", alignItems: "center", gap: 16 }}>
            <Donut data={[
              { value: totalRev, color: "#10b981" },
              { value: cbRev, color: "#ef4444" },
              { value: pendRev, color: "#f59e0b" },
            ]} size={120} />
            <div style={{ fontSize: 11 }}>
              {[["Charged", fmt$(totalRev), "#10b981"], ["Chargebacks", fmt$(cbRev), "#ef4444"], ["Pending", fmt$(pendRev), "#f59e0b"]].map(([l, v, c], i) => (
                <div key={i} style={{ display: "flex", alignItems: "center", gap: 8, marginBottom: 8 }}>
                  <div style={{ width: 10, height: 10, borderRadius: 2, background: c }} />
                  <span>{l}: <strong>{v}</strong></span>
                </div>
              ))}
            </div>
          </div>
        </div>}

        {/* Tasks Panel */}
        <div className="icard fin">
          <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center", marginBottom: 12 }}>
            <div style={{ fontSize: 13, fontWeight: 600 }}>My Open Tasks ({myTasks.length})</div>
            <span style={{ fontSize: 10, color: "var(--t3)" }}>{allOpenTasks.filter(t => t.status === "open").length} total open across team</span>
          </div>
          {myTasks.length === 0 ? (
            <div style={{ padding: 20, textAlign: "center", color: "var(--t3)", fontSize: 12 }}>No open tasks assigned to you</div>
          ) : (
            myTasks.slice(0, 6).map(t => (
              <div key={t.id} style={{ display: "flex", alignItems: "center", gap: 10, padding: "8px 0", borderBottom: "1px solid var(--border)" }}>
                <div style={{ width: 6, height: 6, borderRadius: "50%", background: prioColor(t.priority), flexShrink: 0 }} />
                <div style={{ flex: 1 }}>
                  <div style={{ fontSize: 12, fontWeight: 600 }}>{t.title}</div>
                  <div style={{ fontSize: 10, color: "var(--t3)" }}>{t.type} {t.clientName ? "| " + t.clientName : ""} {t.dueDate ? "| Due: " + t.dueDate : ""}</div>
                </div>
                <span className="tag" style={{ background: prioColor(t.priority) + "22", color: prioColor(t.priority), fontSize: 8 }}>{t.priority}</span>
              </div>
            ))
          )}
        </div>
      </div>

      {/* Bottom Row - Recent Deals + Top Closers */}
      <div className="igrid">
        <div className="icard fin"><div style={{ fontSize: 13, fontWeight: 600, marginBottom: 12 }}>Recent Deals</div>
          {deals.slice(-5).reverse().map(d => <div key={d.id} style={{ display: "flex", justifyContent: "space-between", padding: "6px 0", borderBottom: "1px solid var(--border)", fontSize: 12 }}><div><span style={{ fontWeight: 600 }}>{d.ownerName}</span><span style={{ color: "var(--t3)", marginLeft: 8, fontSize: 10 }}>{d.resortName}</span></div><div style={{ display: "flex", gap: 8, alignItems: "center" }}><span style={{ fontFamily: "'JetBrains Mono',monospace", fontWeight: 700, color: "var(--green)" }}>{fmt$(d.fee)}</span><span className="tag" style={{ background: d.chargedBack === "yes" ? "var(--red-s)" : d.charged === "yes" ? "var(--green-s)" : "var(--amber-s)", color: d.chargedBack === "yes" ? "var(--red)" : d.charged === "yes" ? "var(--green)" : "var(--amber)", fontSize: 8 }}>{d.chargedBack === "yes" ? "CB" : d.charged === "yes" ? "Charged" : "Pending"}</span></div></div>)}
        </div>
        <div className="icard fin"><div style={{ fontSize: 13, fontWeight: 600, marginBottom: 12 }}>Top Closers</div>
          {users.filter(u => u.role === "closer").map(u => { const ct = deals.filter(d => d.closer === u.id && d.charged === "yes").length; const rev = deals.filter(d => d.closer === u.id && d.charged === "yes").reduce((s, d) => s + (Number(d.fee) || 0), 0); return (
            <div key={u.id} style={{ display: "flex", alignItems: "center", gap: 10, padding: "6px 0", borderBottom: "1px solid var(--border)" }}>
              <div style={{ width: 24, height: 24, borderRadius: "50%", background: u.color, display: "flex", alignItems: "center", justifyContent: "center", fontSize: 9, fontWeight: 600, color: "#fff" }}>{u.avatar}</div>
              <div style={{ flex: 1, fontSize: 12, fontWeight: 500 }}>{u.name}</div>
              <div style={{ textAlign: "right" }}><div style={{ fontSize: 13, fontWeight: 700, fontFamily: "'JetBrains Mono',monospace" }}>{ct} deals</div><div style={{ fontSize: 10, color: "var(--green)" }}>{fmt$(rev)}</div></div>
            </div>
          ); })}
        </div>
      </div>
    </div>
  );
}

// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
// LEADS / PIPELINE / DEALS / VERIFICATION / CHAT / USERS â€” compact
// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•

function LeadsView({ leads, allLeads, users, currentUser, P, selected, onSelect, onDisposition, onImport, onAssign, onAddLead, bulkSelected, setBulkSelected, onBulkAssign, onCreateDealFromLead }) {
  const [search, setSearch] = useState(""); const [filter, setFilter] = useState("all"); const [transferCloser, setTransferCloser] = useState("");
  const [folderUser, setFolderUser] = useState("all");
  const [resortFilter, setResortFilter] = useState("all");
  const [ageTab, setAgeTab] = useState("all");
  const [callbackDateTime, setCallbackDateTime] = useState("");
  const closers = users.filter(u => u.role === "closer");
  const isAdmin = P("view_all_leads");
  const agents = users.filter(u => u.role === "fronter" || u.role === "closer");

  // Week boundary for new/old
  const now = new Date();
  const dayOfWeek = now.getDay();
  const weekStart = new Date(now); weekStart.setDate(now.getDate() - ((dayOfWeek + 6) % 7)); weekStart.setHours(0,0,0,0);
  const isThisWeek = (dateStr) => { if (!dateStr) return false; const d = new Date(dateStr); return d >= weekStart; };

  // Get unique resort names from leads
  const resortNames = [...new Set(leads.map(l => l.resort).filter(Boolean))].sort();

  // Apply folder filter, then resort filter, then age filter, then search/status filter
  const folderLeads = folderUser === "all" ? leads : folderUser === "unassigned" ? leads.filter(l => !l.assignedTo) : leads.filter(l => l.assignedTo === folderUser);
  const resortLeads = resortFilter === "all" ? folderLeads : folderLeads.filter(l => l.resort === resortFilter);
  const ageLeads = ageTab === "all" ? resortLeads : ageTab === "new" ? resortLeads.filter(l => isThisWeek(l.createdAt)) : ageTab === "old" ? resortLeads.filter(l => !isThisWeek(l.createdAt)) : resortLeads.filter(l => l.disposition === "Callback");
  const filtered = ageLeads.filter(l => { if (search) { const s = search.toLowerCase(); const phoneMatch = (l.phone1 && l.phone1.includes(s)) || (l.phone2 && l.phone2.includes(s)); if (!l.ownerName.toLowerCase().includes(s) && !l.resort.toLowerCase().includes(s) && !phoneMatch) return false; } if (filter === "undisposed") return !l.disposition; if (filter === "transferred") return l.disposition?.includes("Transfer"); return true; });
  const activeLead = leads.find(l => l.id === selected);
  const canDispo = P("disposition_leads") && activeLead?.assignedTo === currentUser.id && (!activeLead?.disposition || activeLead?.disposition === "Callback");
  const dispoColors = { "Wrong Number": "wr", "Disconnected": "dc", "Right Number": "rn", "Left Voice Mail": "lv", "Callback": "cb", "Closed": "cl", "Transferred to Closer": "tr", "Transferred to Verification": "tr" };
  const isFronter = currentUser.role === "fronter"; const isCloser = currentUser.role === "closer";
  const dispos = isFronter ? FRONTER_DISPOS : isCloser ? CLOSER_DISPOS : [...FRONTER_DISPOS, ...CLOSER_DISPOS].filter((v, i, a) => a.indexOf(v) === i);

  // Folder user info
  const folderUserObj = folderUser !== "all" && folderUser !== "unassigned" ? users.find(u => u.id === folderUser) : null;

  // Callback counts
  const callbackCount = resortLeads.filter(l => l.disposition === "Callback").length;
  const newCount = resortLeads.filter(l => isThisWeek(l.createdAt)).length;
  const oldCount = resortLeads.filter(l => !isThisWeek(l.createdAt)).length;

  return (
    <>
      {/* User Folders Panel â€” only for admins */}
      {isAdmin && (
        <div className="panel" style={{ width: 200, borderRight: "1px solid var(--border)" }}>
          <div className="panel-hd" style={{ padding: "14px 12px 10px" }}>
            <h3 style={{ fontSize: 12 }}>ðŸ“ Lead Folders</h3>
          </div>
          <div className="plist">
            <div className={`item ${folderUser === "all" ? "on" : ""}`} onClick={() => { setFolderUser("all"); onSelect(null); }} style={{ marginBottom: 2 }}>
              <span style={{ width: 24, textAlign: "center", fontSize: 13 }}>ðŸ“‹</span>
              <div className="inf"><div className="nm">All Leads</div><div className="sub">{leads.length} total</div></div>
            </div>
            <div className={`item ${folderUser === "unassigned" ? "on" : ""}`} onClick={() => { setFolderUser("unassigned"); onSelect(null); }} style={{ marginBottom: 2 }}>
              <span style={{ width: 24, textAlign: "center", fontSize: 13 }}>ðŸ“­</span>
              <div className="inf"><div className="nm">Unassigned</div><div className="sub">{leads.filter(l => !l.assignedTo).length} leads</div></div>
            </div>
            <div style={{ fontSize: 10, color: "var(--t3)", textTransform: "uppercase", letterSpacing: ".4px", padding: "10px 10px 4px", fontWeight: 600 }}>Fronters</div>
            {users.filter(u => u.role === "fronter").map(u => {
              const ct = leads.filter(l => l.assignedTo === u.id).length;
              const undisposed = leads.filter(l => l.assignedTo === u.id && !l.disposition).length;
              return (
                <div key={u.id} className={`item ${folderUser === u.id ? "on" : ""}`} onClick={() => { setFolderUser(u.id); onSelect(null); }} style={{ marginBottom: 2 }}>
                  <div className="av" style={{ background: u.color, width: 24, height: 24, fontSize: 9 }}>{u.avatar}</div>
                  <div className="inf"><div className="nm">{u.name}</div><div className="sub">{ct} leads Â· {undisposed} active</div></div>
                </div>
              );
            })}
            <div style={{ fontSize: 10, color: "var(--t3)", textTransform: "uppercase", letterSpacing: ".4px", padding: "10px 10px 4px", fontWeight: 600 }}>Closers</div>
            {users.filter(u => u.role === "closer").map(u => {
              const ct = leads.filter(l => l.assignedTo === u.id).length;
              const undisposed = leads.filter(l => l.assignedTo === u.id && !l.disposition).length;
              return (
                <div key={u.id} className={`item ${folderUser === u.id ? "on" : ""}`} onClick={() => { setFolderUser(u.id); onSelect(null); }} style={{ marginBottom: 2 }}>
                  <div className="av" style={{ background: u.color, width: 24, height: 24, fontSize: 9 }}>{u.avatar}</div>
                  <div className="inf"><div className="nm">{u.name}</div><div className="sub">{ct} leads Â· {undisposed} active</div></div>
                </div>
              );
            })}
          </div>
        </div>
      )}
      {/* Lead List Panel */}
      <div className="panel" style={{ width: isAdmin ? 240 : 260 }}>
        <div className="panel-hd">
          <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center", marginBottom: 8 }}>
            <h3>{folderUserObj ? folderUserObj.name + "'s Leads" : folderUser === "unassigned" ? "Unassigned" : "All Leads"} ({filtered.length})</h3>
            <div style={{ display: "flex", gap: 4 }}>{P("import_csv") && <button className="btn btn-sm btn-p" onClick={onImport}>ðŸ“¥</button>}{P("add_leads") && <button className="btn btn-sm" onClick={onAddLead}>+</button>}</div>
          </div>
          <div className="sbox"><span style={{ color: "var(--t3)", fontSize: 12 }}>âŒ•</span><input placeholder="Name, phone, or resort..." value={search} onChange={e => setSearch(e.target.value)} /></div>
          <div style={{ marginTop: 8 }}>
            <select value={resortFilter} onChange={e => { setResortFilter(e.target.value); onSelect(null); }} style={{ width: "100%", background: "var(--bg-2)", border: "1px solid var(--border)", borderRadius: "var(--r-sm)", padding: "6px 10px", color: "var(--t1)", fontSize: 11, fontFamily: "inherit", outline: 0, cursor: "pointer" }}>
              <option value="all">All Resorts ({folderLeads.length})</option>
              {resortNames.map(r => { const ct = folderLeads.filter(l => l.resort === r).length; return <option key={r} value={r}>{r} ({ct})</option>; })}
            </select>
          </div>
          <div style={{ display: "flex", gap: 3, marginTop: 8 }}>
            {[["all","All",resortLeads.length],["new","New",newCount],["old","Old",oldCount],["callbacks","Callbacks",callbackCount]].map(([k,l,ct]) => (
              <button key={k} className={`btn btn-sm ${ageTab===k?"btn-p":""}`} onClick={() => { setAgeTab(k); onSelect(null); }} style={{ flex: 1, fontSize: 9, padding: "4px 2px" }}>{l} ({ct})</button>
            ))}
          </div>
          <div style={{ display: "flex", gap: 4, marginTop: 8, flexWrap: "wrap" }}>
            {["all","undisposed","transferred"].map(f => <button key={f} className={`btn btn-sm ${filter === f ? "btn-p" : ""}`} onClick={() => setFilter(f)} style={{ textTransform: "capitalize" }}>{f}</button>)}
          </div>
        </div>
        {/* Folder summary card when viewing a specific user */}
        {folderUserObj && (
          <div style={{ padding: "10px 12px", borderBottom: "1px solid var(--border)", background: "var(--bg-2)" }}>
            <div style={{ display: "flex", alignItems: "center", gap: 10, marginBottom: 8 }}>
              <div style={{ width: 32, height: 32, borderRadius: "50%", background: folderUserObj.color, display: "flex", alignItems: "center", justifyContent: "center", fontSize: 11, fontWeight: 600, color: "#fff" }}>{folderUserObj.avatar}</div>
              <div><div style={{ fontSize: 13, fontWeight: 600 }}>{folderUserObj.name}</div><div style={{ fontSize: 10, color: "var(--t3)" }}>{ROLE_LABELS[folderUserObj.role]}</div></div>
            </div>
            <div style={{ display: "flex", gap: 8 }}>
              {[
                [filtered.length, "Total"],
                [filtered.filter(l => !l.disposition).length, "Active"],
                [filtered.filter(l => l.disposition?.includes("Transfer")).length, "Transf."],
              ].map(([v, l], i) => (
                <div key={i} style={{ flex: 1, textAlign: "center", padding: "4px 0", background: "var(--bg-3)", borderRadius: "var(--r-sm)" }}>
                  <div style={{ fontSize: 14, fontWeight: 700, fontFamily: "'JetBrains Mono',monospace" }}>{v}</div>
                  <div style={{ fontSize: 8, color: "var(--t3)", textTransform: "uppercase" }}>{l}</div>
                </div>
              ))}
            </div>
          </div>
        )}
        {/* Bulk assign bar */}
        {isAdmin && bulkSelected && bulkSelected.length > 0 && (
          <div style={{ padding: "8px 12px", borderBottom: "1px solid var(--border)", background: "var(--blue-s)", display: "flex", justifyContent: "space-between", alignItems: "center" }}>
            <span style={{ fontSize: 11, fontWeight: 600 }}>{bulkSelected.length} selected (max 25)</span>
            <div style={{ display: "flex", gap: 4 }}>
              {users.filter(u => u.role === "fronter" || u.role === "closer").map(u => (
                <button key={u.id} className="btn btn-sm" onClick={() => onBulkAssign(u.id)} title={u.name}>{u.avatar}</button>
              ))}
              <button className="btn btn-sm btn-d" onClick={() => setBulkSelected([])}>Clear</button>
            </div>
          </div>
        )}
        <div className="plist">{filtered.map(l => { const au = users.find(u => u.id === l.assignedTo); return (
          <div key={l.id} className={`item ${selected === l.id ? "on" : ""}`} style={{ gap: 6 }}>
            {isAdmin && setBulkSelected && (
              <input type="checkbox" checked={bulkSelected?.includes(l.id) || false} onChange={e => { if (e.target.checked && (bulkSelected?.length || 0) < 25) setBulkSelected(p => [...(p||[]), l.id]); else setBulkSelected(p => (p||[]).filter(x => x !== l.id)); }} style={{ width: 14, height: 14, cursor: "pointer", flexShrink: 0 }} onClick={e => e.stopPropagation()} />
            )}
            <div style={{ display: "flex", alignItems: "center", gap: 10, flex: 1, cursor: "pointer" }} onClick={() => onSelect(l.id)}>
              <div className="av" style={{ background: l.disposition ? (l.disposition.includes("Transfer") ? "var(--pink)" : l.disposition === "Right Number" ? "var(--green)" : "var(--t3)") : "var(--blue)" }}>{l.ownerName.split(" ").map(w => w[0]).join("").slice(0, 2)}</div>
              <div className="inf"><div className="nm">{l.ownerName}</div><div className="sub">{l.resort}</div></div>
              <div style={{ textAlign: "right", flexShrink: 0 }}>{l.disposition && <span className="tag" style={{ background: l.disposition.includes("Transfer") ? "var(--pink-s)" : "var(--bg-3)", color: l.disposition.includes("Transfer") ? "var(--pink)" : "var(--t3)", fontSize: 8 }}>{l.disposition.replace("Transferred to ","-> ")}</span>}{au && !folderUserObj && <div style={{ fontSize: 9, color: "var(--t3)", marginTop: 2 }}>{au.name.split(" ")[0]}</div>}</div>
            </div>
          </div>); })}{filtered.length === 0 && <div className="empty"><div className="icon">ðŸ“‹</div><div className="txt">No leads</div></div>}</div>
      </div>
      {/* Detail Panel */}
      <div className="detail">
        {activeLead ? (<>
          <div className="det-hd fin"><div><h2>{activeLead.ownerName}</h2><div className="sub">{activeLead.resort} Â· {activeLead.resortLocation}</div></div>
            <div style={{ display: "flex", gap: 6 }}>
              {P("assign_leads") && !activeLead.assignedTo && <button className="btn btn-p btn-sm" onClick={() => onAssign([activeLead.id])}>Assign</button>}
              {P("assign_leads") && activeLead.assignedTo && <button className="btn btn-sm" onClick={() => onAssign([activeLead.id])}>Reassign</button>}
              {currentUser.role === "closer" && activeLead.assignedTo === currentUser.id && activeLead.disposition === "Transferred to Closer" && onCreateDealFromLead && (
                <button className="btn btn-sm btn-g" onClick={() => onCreateDealFromLead(activeLead)}>ðŸ“‹ Create Deal Sheet</button>
              )}
            </div>
          </div>
          <div className="det-body fin">
            <div className="igrid"><div className="icard"><div className="lbl">Phone 1</div><div className="val mono">{activeLead.phone1||"-"}</div></div><div className="icard"><div className="lbl">Phone 2</div><div className="val mono">{activeLead.phone2||"-"}</div></div><div className="icard"><div className="lbl">Location</div><div className="val">{activeLead.city}, {activeLead.st} {activeLead.zip}</div></div><div className="icard"><div className="lbl">Assigned To</div><div className="val">{users.find(u => u.id === activeLead.assignedTo)?.name || "Unassigned"}{users.find(u => u.id === activeLead.assignedTo) && <span style={{ fontSize: 10, color: "var(--t3)", marginLeft: 6 }}>({ROLE_LABELS[users.find(u => u.id === activeLead.assignedTo)?.role]})</span>}</div></div><div className="icard"><div className="lbl">Status</div><div className="val">{activeLead.disposition||"Undisposed"}</div></div><div className="icard"><div className="lbl">Source</div><div className="val" style={{ textTransform: "uppercase" }}>{activeLead.source}</div></div><div className="icard"><div className="lbl">Created</div><div className="val">{activeLead.createdAt || "-"}{isThisWeek(activeLead.createdAt) ? <span className="tag" style={{ marginLeft: 6, background: "var(--green-s)", color: "var(--green)", fontSize: 8 }}>NEW</span> : <span className="tag" style={{ marginLeft: 6, background: "var(--bg-3)", color: "var(--t3)", fontSize: 8 }}>OLD</span>}</div></div>{activeLead.callbackDate && <div className="icard"><div className="lbl">Callback Scheduled</div><div className="val" style={{ color: "#2563eb", fontWeight: 600 }}>{activeLead.callbackDate}</div></div>}</div>
            {canDispo && (<><div className="sec-title">Disposition</div><div className="dispo-bar">{dispos.map(d => {
              if (d === "Callback") return <div key={d} style={{ display: "flex", gap: 4, alignItems: "center" }}><input type="datetime-local" value={callbackDateTime} onChange={e => setCallbackDateTime(e.target.value)} style={{ background: "var(--bg-2)", border: "1px solid var(--border)", borderRadius: "var(--r-sm)", padding: "4px 8px", color: "var(--t1)", fontSize: 10, fontFamily: "inherit", outline: 0 }} /><button className="dispo-btn cb" disabled={!callbackDateTime} style={{ opacity: callbackDateTime ? 1 : .4 }} onClick={() => { const dt = new Date(callbackDateTime); const formatted = dt.toLocaleDateString("en-US", { month: "numeric", day: "numeric", year: "numeric" }) + " " + dt.toLocaleTimeString([], { hour: "2-digit", minute: "2-digit" }); onDisposition(activeLead.id, "Callback", null, formatted); setCallbackDateTime(""); }}>Callback</button></div>;
              if (d === "Transferred to Closer") return <div key={d} style={{ display: "flex", gap: 4, alignItems: "center" }}><select value={transferCloser} onChange={e => setTransferCloser(e.target.value)} style={{ background: "var(--bg-2)", border: "1px solid var(--border)", borderRadius: "var(--r-sm)", padding: "5px 8px", color: "var(--t1)", fontSize: 11, fontFamily: "inherit" }}><option value="">Closer...</option>{closers.map(c => <option key={c.id} value={c.id}>{c.name}</option>)}</select><button className="dispo-btn tr" disabled={!transferCloser} style={{ opacity: transferCloser ? 1 : .4 }} onClick={() => { onDisposition(activeLead.id, d, transferCloser); setTransferCloser(""); }}>To Closer</button></div>;
              return <button key={d} className={`dispo-btn ${dispoColors[d]}`} onClick={() => onDisposition(activeLead.id, d)}>{d}</button>;
            })}</div></>)}
            {P("assign_leads") && !activeLead.disposition && (<><div className="sec-title">Admin Disposition</div><div className="dispo-bar">{[...FRONTER_DISPOS,"Transferred to Verification"].filter((v,i,a)=>a.indexOf(v)===i).map(d=>{
              if (d === "Callback") return <div key={d} style={{ display: "flex", gap: 4, alignItems: "center" }}><input type="datetime-local" value={callbackDateTime} onChange={e => setCallbackDateTime(e.target.value)} style={{ background: "var(--bg-2)", border: "1px solid var(--border)", borderRadius: "var(--r-sm)", padding: "4px 8px", color: "var(--t1)", fontSize: 10, fontFamily: "inherit", outline: 0 }} /><button className="dispo-btn cb" disabled={!callbackDateTime} style={{ opacity: callbackDateTime ? 1 : .4 }} onClick={() => { const dt2 = new Date(callbackDateTime); const fmt2 = dt2.toLocaleDateString("en-US", { month: "numeric", day: "numeric", year: "numeric" }) + " " + dt2.toLocaleTimeString([], { hour: "2-digit", minute: "2-digit" }); onDisposition(activeLead.id, "Callback", null, fmt2); setCallbackDateTime(""); }}>Callback</button></div>;
              if(d==="Transferred to Closer")return <div key={d} style={{display:"flex",gap:4,alignItems:"center"}}><select value={transferCloser} onChange={e=>setTransferCloser(e.target.value)} style={{background:"var(--bg-2)",border:"1px solid var(--border)",borderRadius:"var(--r-sm)",padding:"5px 8px",color:"var(--t1)",fontSize:11,fontFamily:"inherit"}}><option value="">Closer...</option>{closers.map(c=><option key={c.id} value={c.id}>{c.name}</option>)}</select><button className="dispo-btn tr" disabled={!transferCloser} style={{opacity:transferCloser?1:.4}} onClick={()=>{onDisposition(activeLead.id,d,transferCloser);setTransferCloser("")}}>To Closer</button></div>;
              return <button key={d} className={`dispo-btn ${dispoColors[d]}`} onClick={()=>onDisposition(activeLead.id,d)}>{d}</button>;
            })}</div></>)}
            {activeLead.disposition && <div style={{ marginTop: 16, padding: 14, background: "var(--bg-3)", borderRadius: "var(--r)", fontSize: 13, color: "var(--t2)" }}>Disposed: <strong style={{ color: "var(--t1)" }}>{activeLead.disposition}</strong>{activeLead.disposition === "Callback" && activeLead.callbackDate && <span style={{ marginLeft: 8, color: "#2563eb", fontWeight: 600 }}>{activeLead.callbackDate}</span>}{activeLead.transferredTo && activeLead.transferredTo !== "verification" && <> {' -> '} <strong style={{ color: "var(--pink)" }}>{users.find(u => u.id === activeLead.transferredTo)?.name}</strong></>}</div>}
          </div>
        </>) : <div className="empty"><div className="icon">âœï¸</div><div className="txt">{folderUserObj ? `Select a lead from ${folderUserObj.name}'s folder` : "Select a lead"}</div></div>}
      </div>
    </>
  );
}

// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
// CLIENTS â€” Charged deals only, admin/master admin access
// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
function ClientsView({ deals, leads, users, currentUser, setDeals, onEdit }) {
  const [selected, setSelected] = useState(null);
  const [search, setSearch] = useState("");
  const [statusTab, setStatusTab] = useState("all");
  const fileRef = useRef(null);
  const chargedClients = deals.filter(d => ["charged","chargeback","chargeback_won","chargeback_lost"].includes(d.status));
  const tabFiltered = statusTab === "all" ? chargedClients : chargedClients.filter(d => d.status === statusTab);
  const filtered = search ? tabFiltered.filter(d => d.ownerName.toLowerCase().includes(search.toLowerCase()) || d.resortName.toLowerCase().includes(search.toLowerCase())) : tabFiltered;
  const ad = deals.find(d => d.id === selected);
  const totalRev = chargedClients.filter(d => d.status === "charged" || d.status === "chargeback_won").reduce((s, d) => s + (Number(d.fee) || 0), 0);
  const cbRev = chargedClients.filter(d => d.status === "chargeback" || d.status === "chargeback_lost").reduce((s, d) => s + (Number(d.fee) || 0), 0);

  const updateStatus = (id, status, extra = {}) => { setDeals(p => p.map(d => d.id === id ? { ...d, status, ...extra } : d)); };

  const handleFileUpload = (e) => {
    if (!selected) return;
    const fileList = Array.from(e.target.files || []);
    if (fileList.length === 0) return;
    const newFiles = fileList.map(f => ({
      id: uid(),
      name: f.name,
      size: (f.size / 1024).toFixed(1) + " KB",
      type: f.type,
      uploadedBy: currentUser.id,
      uploadedAt: nowT(),
      category: f.name.toLowerCase().includes("chargeback") || f.name.toLowerCase().includes("cb") ? "chargeback" : f.name.toLowerCase().includes("packet") ? "chargeback" : "general",
      // In production this would be a URL to uploaded file; for demo we store the name
      url: URL.createObjectURL(f),
    }));
    setDeals(p => p.map(d => d.id === selected ? { ...d, files: [...(d.files || []), ...newFiles] } : d));
    if (fileRef.current) fileRef.current.value = "";
  };

  const removeFile = (fileId) => {
    if (!selected) return;
    setDeals(p => p.map(d => d.id === selected ? { ...d, files: (d.files || []).filter(f => f.id !== fileId) } : d));
  };
  const stColor = s => ({ charged: "var(--green)", chargeback: "var(--red)", chargeback_won: "#10b981", chargeback_lost: "#991b1b" }[s] || "var(--t3)");
  const stLabel = s => ({ charged: "Charged", chargeback: "Chargeback", chargeback_won: "CB Won", chargeback_lost: "CB Lost" }[s] || s);

  return (<>
    <div className="panel">
      <div className="panel-hd">
        <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center", marginBottom: 8 }}>
          <h3>Deals / Clients ({filtered.length})</h3>
        </div>
        <div className="sbox"><span style={{ color: "var(--t3)", fontSize: 12 }}>âŒ•</span><input placeholder="Search clients..." value={search} onChange={e => setSearch(e.target.value)} /></div>
        <div style={{ display: "flex", gap: 3, marginTop: 8, flexWrap: "wrap" }}>
          {[["all","All"],["charged","Charged"],["chargeback","CB"],["chargeback_won","Won"],["chargeback_lost","Lost"]].map(([k,l]) => (
            <button key={k} className={`btn btn-sm ${statusTab===k?"btn-p":""}`} onClick={() => setStatusTab(k)} style={{ fontSize: 9, padding: "3px 6px" }}>{l}</button>
          ))}
        </div>
        <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr", gap: 6, marginTop: 10 }}>
          <div style={{ textAlign: "center", padding: "6px 0", background: "var(--green-s)", borderRadius: "var(--r-sm)" }}>
            <div style={{ fontSize: 14, fontWeight: 700, fontFamily: "'JetBrains Mono',monospace", color: "var(--green)" }}>{fmt$(totalRev)}</div>
            <div style={{ fontSize: 8, color: "var(--t3)", textTransform: "uppercase" }}>Charged + Won</div>
          </div>
          <div style={{ textAlign: "center", padding: "6px 0", background: "var(--red-s)", borderRadius: "var(--r-sm)" }}>
            <div style={{ fontSize: 14, fontWeight: 700, fontFamily: "'JetBrains Mono',monospace", color: "var(--red)" }}>{fmt$(cbRev)}</div>
            <div style={{ fontSize: 8, color: "var(--t3)", textTransform: "uppercase" }}>CB + Lost</div>
          </div>
        </div>
      </div>
      <div className="plist">{filtered.map(d => (
        <div key={d.id} className={`item ${selected === d.id ? "on" : ""}`} onClick={() => setSelected(d.id)}>
          <div className="av" style={{ background: stColor(d.status) }}>{d.ownerName.split(" ").map(w => w[0]).join("").slice(0, 2)}</div>
          <div className="inf"><div className="nm">{d.ownerName}</div><div className="sub">{d.resortName}</div></div>
          <div style={{ textAlign: "right" }}>
            <div style={{ fontFamily: "'JetBrains Mono',monospace", fontWeight: 700, color: "var(--green)", fontSize: 12 }}>{fmt$(d.fee)}</div>
            <span className="tag" style={{ background: stColor(d.status) + "22", color: stColor(d.status), fontSize: 8 }}>{stLabel(d.status)}</span>
          </div>
        </div>
      ))}{filtered.length === 0 && <div className="empty"><div className="icon">ðŸ’°</div><div className="txt">No charged clients</div></div>}</div>
    </div>
    <div className="detail">
      {ad ? (<>
        <div className="det-hd fin">
          <div><h2>{ad.ownerName}</h2><div className="sub">{ad.resortName} | V#{ad.verificationNum || "-"} | <span style={{ color: stColor(ad.status), fontWeight: 700 }}>{stLabel(ad.status).toUpperCase()}</span></div></div>
          <div style={{ display: "flex", gap: 6 }}>
            <button className="btn btn-sm btn-p" onClick={() => onEdit(ad)}>âœŽ Edit</button>
          </div>
        </div>
        <div className="det-body fin">
          {/* Status change bar */}
          <div style={{ marginBottom: 14, padding: 12, background: "var(--bg-2)", borderRadius: "var(--r)", border: "1px solid var(--border)" }}>
            <div style={{ fontSize: 11, fontWeight: 600, marginBottom: 8, color: "var(--t2)" }}>Change Status</div>
            <div style={{ display: "flex", gap: 6, flexWrap: "wrap" }}>
              {ad.status !== "charged" && <button className="btn btn-sm btn-g" onClick={() => updateStatus(ad.id, "charged", { charged: "yes", chargedBack: "no", chargedDate: ad.chargedDate || todayStr() })}>Charged</button>}
              {ad.status !== "chargeback" && <button className="btn btn-sm btn-d" onClick={() => updateStatus(ad.id, "chargeback", { chargedBack: "yes" })}>Chargeback</button>}
              {ad.status !== "chargeback_won" && <button className="btn btn-sm" style={{ borderColor: "#10b981", color: "#10b981", background: "rgba(16,185,129,.08)" }} onClick={() => updateStatus(ad.id, "chargeback_won", { chargedBack: "no", charged: "yes" })}>CB Won</button>}
              {ad.status !== "chargeback_lost" && <button className="btn btn-sm" style={{ borderColor: "#991b1b", color: "#991b1b", background: "rgba(153,27,27,.08)" }} onClick={() => updateStatus(ad.id, "chargeback_lost")}>CB Lost</button>}
            </div>
          </div>
          <div className="igrid c3">
            <div className="icard"><div className="lbl">Fee</div><div className="val lg" style={{ color: "var(--green)" }}>{fmt$(ad.fee)}</div></div>
            <div className="icard"><div className="lbl">Charged Date</div><div className="val">{ad.chargedDate || "-"}</div></div>
            <div className="icard"><div className="lbl">Was VD</div><div className="val">{ad.wasVD || "No"}</div></div>
          </div>
          <div className="sec-title">Team</div>
          <div className="igrid c3">
            <div className="icard"><div className="lbl">Fronter</div><div className="val">{users.find(u => u.id === ad.fronter)?.name || "Self"}</div></div>
            <div className="icard"><div className="lbl">Closer</div><div className="val">{users.find(u => u.id === ad.closer)?.name || "-"}</div></div>
            <div className="icard"><div className="lbl">Admin</div><div className="val">{users.find(u => u.id === ad.assignedAdmin)?.name || "-"}</div></div>
          </div>
          <div className="sec-title">Owner Info</div>
          <div className="igrid">
            <div className="icard"><div className="lbl">Name</div><div className="val">{ad.ownerName}</div></div>
            <div className="icard"><div className="lbl">Email</div><div className="val mono" style={{ fontSize: 11 }}>{ad.email || "-"}</div></div>
            <div className="icard"><div className="lbl">Phone</div><div className="val mono">{ad.primaryPhone}</div></div>
            <div className="icard"><div className="lbl">Address</div><div className="val">{ad.mailingAddress} {ad.cityStateZip}</div></div>
          </div>
          <div className="sec-title">Property</div>
          <div className="igrid c3">
            <div className="icard"><div className="lbl">Resort</div><div className="val">{ad.resortName}</div></div>
            <div className="icard"><div className="lbl">Location</div><div className="val">{ad.resortCityState}</div></div>
            <div className="icard"><div className="lbl">Bed/Bath</div><div className="val">{ad.bedBath || "-"}</div></div>
            <div className="icard"><div className="lbl">Weeks</div><div className="val">{ad.weeks || "-"}</div></div>
            <div className="icard"><div className="lbl">Usage</div><div className="val">{ad.usage || "-"}</div></div>
            <div className="icard"><div className="lbl">Exchange</div><div className="val">{ad.exchangeGroup || "-"}</div></div>
          </div>
          <div className="sec-title">Payment / Card</div>
          <div className="igrid">
            <div className="icard"><div className="lbl">Name on Card</div><div className="val">{ad.nameOnCard || "-"}</div></div>
            <div className="icard"><div className="lbl">Card</div><div className="val mono">{ad.cardType} {ad.cardNumber || "-"}</div></div>
            <div className="icard"><div className="lbl">Exp / CV2</div><div className="val mono">{ad.expDate || "-"} / {ad.cv2 || "-"}</div></div>
            <div className="icard"><div className="lbl">Bank</div><div className="val">{ad.bank || "-"}</div></div>
            <div className="icard"><div className="lbl">Billing</div><div className="val">{ad.billingAddress || "-"}</div></div>
            <div className="icard"><div className="lbl">Merchant</div><div className="val mono">{ad.merchant || "-"}</div></div>
          </div>
          {ad.loginInfo && (<><div className="sec-title">Login Info</div><div className="icard"><div className="val mono" style={{ fontSize: 12, lineHeight: 1.6 }}>{ad.loginInfo}</div></div></>)}
          {ad.correspondence?.length > 0 && (<><div className="sec-title">Correspondence</div>{ad.correspondence.map((c, i) => <div key={i} className="corr-item">{c}</div>)}</>)}
          {ad.notes && (<><div className="sec-title">Notes</div><div style={{ background: "var(--bg-2)", border: "1px solid var(--border)", borderRadius: "var(--r)", padding: 12, fontSize: 12, color: "var(--t2)", lineHeight: 1.6 }}>{ad.notes}</div></>)}

          {/* Files / Documents / Chargeback Packets */}
          <div className="sec-title" style={{ display: "flex", justifyContent: "space-between", alignItems: "center" }}>
            <span>Documents & Files ({(ad.files || []).length})</span>
            <div>
              <input type="file" ref={fileRef} onChange={handleFileUpload} accept=".pdf,.doc,.docx,.png,.jpg,.jpeg,.xlsx,.csv" multiple style={{ display: "none" }} />
              <button className="btn btn-sm btn-p" onClick={() => fileRef.current?.click()}>ðŸ“Ž Upload Files</button>
            </div>
          </div>
          {(ad.files || []).length > 0 ? (
            <div style={{ marginBottom: 12 }}>
              {/* Chargeback / Challenge Packet files */}
              {(ad.files || []).filter(f => f.category === "chargeback").length > 0 && (
                <div style={{ marginBottom: 10 }}>
                  <div style={{ fontSize: 10, fontWeight: 600, color: "var(--red)", textTransform: "uppercase", letterSpacing: ".4px", marginBottom: 6 }}>Chargeback Challenge Packets</div>
                  {(ad.files || []).filter(f => f.category === "chargeback").map(f => (
                    <div key={f.id} style={{ display: "flex", alignItems: "center", gap: 10, padding: "8px 10px", background: "var(--red-s)", border: "1px solid rgba(239,68,68,.2)", borderRadius: "var(--r-sm)", marginBottom: 4 }}>
                      <span style={{ fontSize: 16 }}>ðŸ“„</span>
                      <div style={{ flex: 1 }}>
                        <div style={{ fontSize: 12, fontWeight: 600, color: "var(--t1)" }}>{f.name}</div>
                        <div style={{ fontSize: 10, color: "var(--t3)" }}>{f.size} | {users.find(u => u.id === f.uploadedBy)?.name || "Unknown"} | {f.uploadedAt}</div>
                      </div>
                      {f.url && <a href={f.url} target="_blank" rel="noopener noreferrer" style={{ fontSize: 10, color: "var(--blue)", textDecoration: "none", fontWeight: 600 }}>View</a>}
                      <span onClick={() => removeFile(f.id)} style={{ fontSize: 12, color: "var(--red)", cursor: "pointer", padding: "2px 4px" }} title="Remove">âœ•</span>
                    </div>
                  ))}
                </div>
              )}
              {/* General files */}
              {(ad.files || []).filter(f => f.category !== "chargeback").length > 0 && (
                <div>
                  <div style={{ fontSize: 10, fontWeight: 600, color: "var(--t3)", textTransform: "uppercase", letterSpacing: ".4px", marginBottom: 6 }}>General Documents</div>
                  {(ad.files || []).filter(f => f.category !== "chargeback").map(f => (
                    <div key={f.id} style={{ display: "flex", alignItems: "center", gap: 10, padding: "8px 10px", background: "var(--bg-2)", border: "1px solid var(--border)", borderRadius: "var(--r-sm)", marginBottom: 4 }}>
                      <span style={{ fontSize: 16 }}>{f.type?.includes("pdf") ? "ðŸ“•" : f.type?.includes("image") ? "ðŸ–¼" : f.type?.includes("sheet") || f.type?.includes("csv") ? "ðŸ“Š" : "ðŸ“„"}</span>
                      <div style={{ flex: 1 }}>
                        <div style={{ fontSize: 12, fontWeight: 600, color: "var(--t1)" }}>{f.name}</div>
                        <div style={{ fontSize: 10, color: "var(--t3)" }}>{f.size} | {users.find(u => u.id === f.uploadedBy)?.name || "Unknown"} | {f.uploadedAt}</div>
                      </div>
                      {f.url && <a href={f.url} target="_blank" rel="noopener noreferrer" style={{ fontSize: 10, color: "var(--blue)", textDecoration: "none", fontWeight: 600 }}>View</a>}
                      <span onClick={() => removeFile(f.id)} style={{ fontSize: 12, color: "var(--red)", cursor: "pointer", padding: "2px 4px" }} title="Remove">âœ•</span>
                    </div>
                  ))}
                </div>
              )}
            </div>
          ) : (
            <div style={{ padding: 16, textAlign: "center", color: "var(--t3)", fontSize: 11, background: "var(--bg-2)", borderRadius: "var(--r)", marginBottom: 12 }}>No files uploaded. Click "Upload Files" to add PDFs, docs, or images.</div>
          )}
        </div>
      </>) : <div className="empty"><div className="icon">ðŸ’°</div><div className="txt">Select a client to view deal details</div></div>}
    </div>
  </>);
}

function PipelineView({ leads, deals, users, currentUser, P, onSelect }) {
  const isAdmin = P("view_all_leads");
  const isCloser = currentUser.role === "closer";
  const isFronter = currentUser.role === "fronter";

  // Admin/Master Admin: pending deals transferred to admin with no charge status
  if (isAdmin) {
    const pendingDeals = deals.filter(d => d.status === "pending_admin" || d.status === "in_verification");
    const myPending = currentUser.role === "master_admin" ? pendingDeals : pendingDeals.filter(d => d.assignedAdmin === currentUser.id);
    return (
      <div style={{ flex: 1, display: "flex", flexDirection: "column", overflow: "hidden" }}>
        <div style={{ padding: "16px 20px", borderBottom: "1px solid var(--border)", flexShrink: 0 }}>
          <h2 style={{ fontSize: 16, fontWeight: 700 }}>Pipeline - Pending Deals</h2>
          <p style={{ fontSize: 12, color: "var(--t3)", marginTop: 2 }}>{myPending.length} deals awaiting verification & charge</p>
        </div>
        <div className="tbl-wrap" style={{ flex: 1, overflow: "auto", padding: "0 20px 20px" }}>
          <table className="tbl">
            <thead><tr><th>Owner</th><th>Resort</th><th>Fee</th><th>Closer</th><th>Admin</th><th>Status</th><th>Card Info</th><th>Date</th></tr></thead>
            <tbody>
              {myPending.map(d => {
                const statusCol = d.status === "pending_admin" ? "var(--amber)" : "var(--blue)";
                const statusLbl = d.status === "pending_admin" ? "Pending Admin" : "In Verification";
                return (
                  <tr key={d.id}>
                    <td style={{ fontWeight: 600 }}>{d.ownerName}</td>
                    <td style={{ fontSize: 11 }}>{d.resortName}</td>
                    <td style={{ fontFamily: "'JetBrains Mono',monospace", fontWeight: 600, color: "var(--green)" }}>{fmt$(d.fee)}</td>
                    <td>{users.find(u => u.id === d.closer)?.name || "-"}</td>
                    <td>{users.find(u => u.id === d.assignedAdmin)?.name || "-"}</td>
                    <td><span className="tag" style={{ background: statusCol + "22", color: statusCol }}>{statusLbl}</span></td>
                    <td style={{ fontSize: 10, fontFamily: "'JetBrains Mono',monospace" }}>{d.cardType} ****{d.cardNumber ? d.cardNumber.slice(-4) : "----"}</td>
                    <td style={{ color: "var(--t3)", fontSize: 11 }}>{d.timestamp}</td>
                  </tr>
                );
              })}
              {myPending.length === 0 && <tr><td colSpan={8} style={{ textAlign: "center", color: "var(--t3)", padding: 40 }}>No pending deals in pipeline</td></tr>}
            </tbody>
          </table>
        </div>
      </div>
    );
  }

  // Fronter/Closer: leads with Callback disposition
  const callbackLeads = isFronter
    ? leads.filter(l => l.disposition === "Callback" && l.assignedTo === currentUser.id)
    : isCloser
    ? leads.filter(l => l.disposition === "Callback" && l.assignedTo === currentUser.id)
    : leads.filter(l => l.disposition === "Callback");

  return (
    <div style={{ flex: 1, display: "flex", flexDirection: "column", overflow: "hidden" }}>
      <div style={{ padding: "16px 20px", borderBottom: "1px solid var(--border)", flexShrink: 0 }}>
        <h2 style={{ fontSize: 16, fontWeight: 700 }}>Pipeline - Callbacks</h2>
        <p style={{ fontSize: 12, color: "var(--t3)", marginTop: 2 }}>{callbackLeads.length} leads scheduled for callback</p>
      </div>
      <div className="tbl-wrap" style={{ flex: 1, overflow: "auto", padding: "0 20px 20px" }}>
        <table className="tbl">
          <thead><tr><th>Owner</th><th>Resort</th><th>Phone 1</th><th>Phone 2</th><th>Location</th><th>Callback Date</th><th>Created</th></tr></thead>
          <tbody>
            {callbackLeads.sort((a, b) => { if (!a.callbackDate) return 1; if (!b.callbackDate) return -1; return new Date(a.callbackDate) - new Date(b.callbackDate); }).map(l => {
              const isOverdue = l.callbackDate && new Date(l.callbackDate) < new Date();
              return (
                <tr key={l.id} onClick={() => onSelect(l.id)} style={{ cursor: "pointer" }}>
                  <td style={{ fontWeight: 600 }}>{l.ownerName}</td>
                  <td>{l.resort}</td>
                  <td style={{ fontFamily: "'JetBrains Mono',monospace", fontSize: 11 }}>{l.phone1 || "-"}</td>
                  <td style={{ fontFamily: "'JetBrains Mono',monospace", fontSize: 11 }}>{l.phone2 || "-"}</td>
                  <td>{l.resortLocation || l.city + ", " + l.st}</td>
                  <td style={{ fontWeight: 600, color: isOverdue ? "var(--red)" : "#2563eb" }}>{l.callbackDate || "Not set"}{isOverdue && <span style={{ marginLeft: 6, fontSize: 9, color: "var(--red)" }}>OVERDUE</span>}</td>
                  <td style={{ color: "var(--t3)", fontSize: 11 }}>{l.createdAt || "-"}</td>
                </tr>
              );
            })}
            {callbackLeads.length === 0 && <tr><td colSpan={7} style={{ textAlign: "center", color: "var(--t3)", padding: 40 }}>No callback leads in your pipeline</td></tr>}
          </tbody>
        </table>
      </div>
    </div>
  );
}

function DealsView({ deals, users, currentUser, P, selected, onSelect, onNew, onEdit, dealStatuses }) {
  const isAdmin = P("view_all_leads");
  // Fronters/closers: hide charged deals (those go to Clients tab for admins only)
  const visibleDeals = isAdmin ? deals : deals.filter(d => d.status !== "charged" && d.status !== "chargeback");
  const myDeals = currentUser.role === "closer" ? visibleDeals.filter(d => d.closer === currentUser.id) : currentUser.role === "fronter" ? visibleDeals.filter(d => d.fronter === currentUser.id) : visibleDeals;
  const [statusFilter, setStatusFilter] = useState("all");
  const filteredDeals = statusFilter === "all" ? myDeals : myDeals.filter(d => d.status === statusFilter);
  const ad = deals.find(d => d.id === selected);
  const getStatusColor = s => { const found = dealStatuses?.find(ds => ds.id === s); return found ? found.color : "var(--t3)"; };
  const getStatusLabel = s => { const found = dealStatuses?.find(ds => ds.id === s); return found ? found.label : s; };
  const allStatusIds = dealStatuses ? dealStatuses.map(ds => ds.id) : ["pending_admin","in_verification","charged","chargeback","cancelled"];

  const isCloser = currentUser.role === "closer";

  return (<>
    <div className="panel">
      <div className="panel-hd">
        <div style={{display:"flex",justifyContent:"space-between",alignItems:"center",marginBottom:8}}><h3>Deals ({filteredDeals.length})</h3>{P("create_deals")&&<button className="btn btn-sm btn-p" onClick={onNew}>+ New</button>}</div>
        {isAdmin && <div style={{ display: "flex", gap: 4, flexWrap: "wrap" }}>
          <button className={`btn btn-sm ${statusFilter==="all"?"btn-p":""}`} onClick={() => setStatusFilter("all")} style={{ fontSize: 9 }}>All</button>
          {allStatusIds.map(s => <button key={s} className={`btn btn-sm ${statusFilter===s?"btn-p":""}`} onClick={() => setStatusFilter(s)} style={{ fontSize: 9 }}>{getStatusLabel(s)}</button>)}
        </div>}
      </div>
      <div className="plist">{filteredDeals.map(d=><div key={d.id} className={`item ${selected===d.id?"on":""}`} onClick={()=>onSelect(d.id)}>
        {!isCloser && <div className="av" style={{background:getStatusColor(d.status)}}>{d.ownerName.split(" ").map(w=>w[0]).join("").slice(0,2)}</div>}
        <div className="inf">
          <div className="nm">{d.ownerName}</div>
          <div className="sub">{isCloser ? fmt$(d.fee) : d.resortName + " Â· " + fmt$(d.fee)}</div>
        </div>
        <div style={{ textAlign: "right" }}>
          <span className="tag" style={{background:getStatusColor(d.status)+"22",color:getStatusColor(d.status),fontSize:8}}>{getStatusLabel(d.status)}</span>
        </div>
      </div>)}</div>
    </div>
    <div className="detail">
      {ad?(<>
        <div className="det-hd fin">
          <div><h2>{ad.ownerName}</h2><div className="sub">{isAdmin ? ad.resortName + " Â· V#" + (ad.verificationNum||"-") : fmt$(ad.fee) + " Â· Submitted"}</div></div>
          <div style={{display:"flex",gap:6}}>
            {isAdmin&&<button className="btn btn-sm btn-p" onClick={()=>onEdit(ad)}>âœŽ Edit Deal</button>}
          </div>
        </div>
        <div className="det-body fin">
          <div className="igrid c3">
            <div className="icard"><div className="lbl">Fee</div><div className="val lg" style={{color:"var(--green)"}}>{fmt$(ad.fee)}</div></div>
            <div className="icard"><div className="lbl">Status</div><div className="val"><span className="tag" style={{background:getStatusColor(ad.status)+"22",color:getStatusColor(ad.status)}}>{(ad.status||"").replace("_"," ").toUpperCase()}</span></div></div>
            <div className="icard"><div className="lbl">Was VD</div><div className="val">{ad.wasVD||"No"}</div></div>
          </div>
          {/* Closer: limited view */}
          {isCloser && (
            <div className="igrid">
              <div className="icard"><div className="lbl">Assigned Admin</div><div className="val">{users.find(u=>u.id===ad.assignedAdmin)?.name||"Pending"}</div></div>
              <div className="icard"><div className="lbl">Date Submitted</div><div className="val">{ad.timestamp||"-"}</div></div>
            </div>
          )}
          {/* Admin: full view */}
          {isAdmin && (<>
          <div className="igrid c3">
            <div className="icard"><div className="lbl">Fronter</div><div className="val">{users.find(u=>u.id===ad.fronter)?.name||"Self"}</div></div>
            <div className="icard"><div className="lbl">Closer</div><div className="val">{users.find(u=>u.id===ad.closer)?.name||"-"}</div></div>
            <div className="icard"><div className="lbl">Assigned Admin</div><div className="val">{users.find(u=>u.id===ad.assignedAdmin)?.name||"-"}</div></div>
          </div>
          <div className="igrid">
            <div className="icard"><div className="lbl">Charged</div><div className="val" style={{color:ad.charged==="yes"?"var(--green)":"var(--amber)"}}>{ad.charged==="yes"?"Yes":"No"}{ad.chargedDate && " - "+ad.chargedDate}</div></div>
            <div className="icard"><div className="lbl">Chargeback</div><div className="val" style={{color:ad.chargedBack==="yes"?"var(--red)":"var(--green)"}}>{ad.chargedBack==="yes"?"Yes":"No"}</div></div>
          </div>
          {(ad.nameOnCard || ad.cardNumber) && (<>
            <div className="sec-title">Payment / Card Info</div>
            <div className="igrid">
              <div className="icard"><div className="lbl">Name on Card</div><div className="val">{ad.nameOnCard||"-"}</div></div>
              <div className="icard"><div className="lbl">Card</div><div className="val mono">{ad.cardType} {ad.cardNumber||"-"}</div></div>
              <div className="icard"><div className="lbl">Exp / CV2</div><div className="val mono">{ad.expDate||"-"} / {ad.cv2||"-"}</div></div>
              <div className="icard"><div className="lbl">Bank</div><div className="val">{ad.bank||"-"}</div></div>
              <div className="icard"><div className="lbl">Billing</div><div className="val">{ad.billingAddress||"-"}</div></div>
              <div className="icard"><div className="lbl">Merchant</div><div className="val mono">{ad.merchant||"-"}</div></div>
            </div>
            {ad.bank2 && <div className="igrid" style={{marginTop:6}}>
              <div className="icard"><div className="lbl">2nd Bank</div><div className="val">{ad.bank2}</div></div>
              <div className="icard"><div className="lbl">2nd Card</div><div className="val mono">{ad.cardNumber2||"-"}</div></div>
              <div className="icard"><div className="lbl">2nd Exp/CV2</div><div className="val mono">{ad.expDate2||"-"} / {ad.cv2_2||"-"}</div></div>
            </div>}
          </>)}
          <div className="sec-title">Owner Info</div>
          <div className="igrid"><div className="icard"><div className="lbl">Address</div><div className="val">{ad.mailingAddress}</div></div><div className="icard"><div className="lbl">City/St/Zip</div><div className="val">{ad.cityStateZip}</div></div><div className="icard"><div className="lbl">Phone</div><div className="val mono">{ad.primaryPhone}</div></div><div className="icard"><div className="lbl">Email</div><div className="val mono" style={{fontSize:11}}>{ad.email}</div></div></div>
          <div className="sec-title">Property</div>
          <div className="igrid c3"><div className="icard"><div className="lbl">Resort</div><div className="val">{ad.resortName}</div></div><div className="icard"><div className="lbl">Location</div><div className="val">{ad.resortCityState}</div></div><div className="icard"><div className="lbl">Bed/Bath</div><div className="val">{ad.bedBath||"-"}</div></div><div className="icard"><div className="lbl">Weeks</div><div className="val">{ad.weeks||"-"}</div></div><div className="icard"><div className="lbl">Usage</div><div className="val">{ad.usage||"-"}</div></div><div className="icard"><div className="lbl">Exchange</div><div className="val">{ad.exchangeGroup||"-"}</div></div></div>
          {ad.loginInfo && (<><div className="sec-title">Login Info</div><div className="icard"><div className="val mono" style={{fontSize:12,lineHeight:1.6}}>{ad.loginInfo}</div></div></>)}
          {(ad.login || ad.appLogin) && (
            <div className="igrid c3" style={{marginTop:8}}>
              {ad.login&&<div className="icard"><div className="lbl">Login URL</div><div className="val mono" style={{fontSize:10,wordBreak:"break-all"}}>{ad.login}</div></div>}
              {ad.appLogin&&<div className="icard"><div className="lbl">App Login</div><div className="val mono">{ad.appLogin}</div></div>}
            </div>
          )}
          </>)}
          {ad.correspondence && ad.correspondence.length > 0 && (<><div className="sec-title">Correspondence</div>{ad.correspondence.map((c,i)=><div key={i} className="corr-item">{c}</div>)}</>)}
          {ad.notes && (<><div className="sec-title">Notes</div><div style={{background:"var(--bg-2)",border:"1px solid var(--border)",borderRadius:"var(--r)",padding:12,fontSize:12,color:"var(--t2)",lineHeight:1.6}}>{ad.notes}</div></>)}
        </div>
      </>):<div className="empty"><div className="icon">ðŸ“‹</div><div className="txt">Select a deal</div></div>}
    </div>
  </>);
}

function VerificationView({ deals, users, setDeals, P, currentUser, dealStatuses }) {
  const [tab, setTab] = useState("pending");
  const [page, setPage] = useState(0);
  const [dateFrom, setDateFrom] = useState("");
  const [dateTo, setDateTo] = useState("");
  const [selDeal, setSelDeal] = useState(null);
  const [noteInput, setNoteInput] = useState("");
  const PAGE_SIZE = 100;
  const admins = users.filter(u => u.role === "admin" || u.role === "admin_limited" || u.role === "master_admin");
  const isAdmin = P("toggle_charged");
  const isCloser = currentUser.role === "closer";

  // Week boundary for closer filtering
  const now = new Date();
  const dayOfWeek = now.getDay();
  const weekStart = new Date(now); weekStart.setDate(now.getDate() - ((dayOfWeek + 6) % 7)); weekStart.setHours(0,0,0,0);
  const isThisWeek = (dateStr) => { if (!dateStr) return true; const d = new Date(dateStr); return d >= weekStart; };

  // Date range filter for admin
  const inDateRange = (d) => {
    if (!dateFrom && !dateTo) return true;
    const dt = new Date(d.chargedDate || d.timestamp);
    if (dateFrom && dt < new Date(dateFrom)) return false;
    if (dateTo) { const to = new Date(dateTo); to.setHours(23,59,59); if (dt > to) return false; }
    return true;
  };

  // Base deals: closer sees only this week + own deals; admin sees all (with optional date filter)
  const baseDeals = isCloser
    ? deals.filter(d => (d.closer === currentUser.id) && isThisWeek(d.chargedDate || d.timestamp))
    : deals.filter(d => inDateRange(d));

  const pendingDeals = baseDeals.filter(d => d.status === "pending_admin" && (P("master_override") || d.assignedAdmin === currentUser.id));
  const inVerif = baseDeals.filter(d => d.status === "in_verification" && (P("master_override") || d.assignedAdmin === currentUser.id));
  const chargedDeals = baseDeals.filter(d => d.status === "charged");
  const cbDeals = baseDeals.filter(d => d.status === "chargeback");
  const cancelledDeals = baseDeals.filter(d => d.status === "cancelled");
  const allDeals = baseDeals;

  const updateStatus = (id, status, extra = {}) => {
    setDeals(p => p.map(d => d.id === id ? { ...d, status, ...extra } : d));
  };

  const addVerifNote = () => {
    if (!noteInput.trim() || !selDeal) return;
    const entry = nowT() + " - " + currentUser.name + ": " + noteInput;
    setDeals(p => p.map(d => d.id === selDeal ? { ...d, correspondence: [...(d.correspondence || []), entry] } : d));
    setNoteInput("");
  };

  const activeDeal = deals.find(d => d.id === selDeal);

  const statusColor = s => ({ pending_admin: "var(--amber)", in_verification: "var(--blue)", charged: "var(--green)", chargeback: "var(--red)", cancelled: "var(--t3)" }[s] || "var(--t3)");
  const statusLabel = s => ({ pending_admin: "Pending Admin", in_verification: "In Verification", charged: "Charged", chargeback: "Chargeback", cancelled: "Cancelled" }[s] || s);

  const allFiltered = tab === "pending" ? pendingDeals : tab === "verifying" ? inVerif : tab === "charged" ? chargedDeals : tab === "chargeback" ? cbDeals : tab === "cancelled" ? cancelledDeals : allDeals;
  const totalPages = Math.ceil(allFiltered.length / PAGE_SIZE);
  const filtered = allFiltered.slice(page * PAGE_SIZE, (page + 1) * PAGE_SIZE);

  // Reset page when tab or date changes
  const switchTab = (t) => { setTab(t); setPage(0); };

  const weekLabel = weekStart.toLocaleDateString("en-US", { month: "short", day: "numeric" }) + " - " + now.toLocaleDateString("en-US", { month: "short", day: "numeric" });

  return <div style={{ flex: 1, display: "flex", flexDirection: "column", overflow: "hidden" }}>
    <div style={{ padding: "16px 20px", borderBottom: "1px solid var(--border)", flexShrink: 0 }}>
      <div style={{ display: "flex", justifyContent: "space-between", alignItems: "flex-start" }}>
        <div>
          <h2 style={{ fontSize: 16, fontWeight: 700 }}>Verification & Charging</h2>
          <p style={{ fontSize: 12, color: "var(--t3)", marginTop: 2 }}>
            {isCloser ? `This week (${weekLabel}) | ` : ""}{pendingDeals.length} pending | {inVerif.length} verifying | {chargedDeals.length} charged | {cbDeals.length} CB | {cancelledDeals.length} cancelled
          </p>
        </div>
        {/* Date range picker for admin/master admin */}
        {isAdmin && !isCloser && (
          <div style={{ display: "flex", gap: 6, alignItems: "center" }}>
            <label style={{ fontSize: 10, color: "var(--t3)" }}>From:</label>
            <input type="date" value={dateFrom} onChange={e => { setDateFrom(e.target.value); setPage(0); }} style={{ background: "var(--bg-2)", border: "1px solid var(--border)", borderRadius: "var(--r-sm)", padding: "4px 8px", color: "var(--t1)", fontSize: 11, fontFamily: "inherit", outline: 0 }} />
            <label style={{ fontSize: 10, color: "var(--t3)" }}>To:</label>
            <input type="date" value={dateTo} onChange={e => { setDateTo(e.target.value); setPage(0); }} style={{ background: "var(--bg-2)", border: "1px solid var(--border)", borderRadius: "var(--r-sm)", padding: "4px 8px", color: "var(--t1)", fontSize: 11, fontFamily: "inherit", outline: 0 }} />
            {(dateFrom || dateTo) && <button className="btn btn-sm" onClick={() => { setDateFrom(""); setDateTo(""); setPage(0); }}>Clear</button>}
          </div>
        )}
      </div>
    </div>
    <div className="tabs" style={{ flexWrap: "wrap" }}>
      {[["pending", "Pending (" + pendingDeals.length + ")"], ["verifying", "Verifying (" + inVerif.length + ")"], ["charged", "Charged (" + chargedDeals.length + ")"], ["chargeback", "CB (" + cbDeals.length + ")"], ["cancelled", "Cancelled (" + cancelledDeals.length + ")"], ["all", "All (" + allDeals.length + ")"]].map(([k, l]) => (
        <div key={k} className={`tab ${tab === k ? "on" : ""}`} onClick={() => switchTab(k)}>{l}</div>
      ))}
    </div>
    {/* Pagination bar */}
    {isAdmin && totalPages > 1 && (
      <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center", padding: "8px 20px", borderBottom: "1px solid var(--border)", flexShrink: 0 }}>
        <span style={{ fontSize: 11, color: "var(--t3)" }}>Showing {page * PAGE_SIZE + 1}-{Math.min((page + 1) * PAGE_SIZE, allFiltered.length)} of {allFiltered.length}</span>
        <div style={{ display: "flex", gap: 4 }}>
          <button className="btn btn-sm" disabled={page === 0} onClick={() => setPage(p => p - 1)}>â† Prev</button>
          {Array.from({ length: Math.min(totalPages, 5) }, (_, i) => {
            const pg = totalPages <= 5 ? i : page <= 2 ? i : page >= totalPages - 3 ? totalPages - 5 + i : page - 2 + i;
            return <button key={pg} className={`btn btn-sm ${pg === page ? "btn-p" : ""}`} onClick={() => setPage(pg)} style={{ minWidth: 30 }}>{pg + 1}</button>;
          })}
          <button className="btn btn-sm" disabled={page >= totalPages - 1} onClick={() => setPage(p => p + 1)}>Next â†’</button>
        </div>
      </div>
    )}
    <div style={{ flex: 1, display: "flex", overflow: "hidden" }}>
      <div style={{ flex: activeDeal ? "0 0 55%" : 1, overflowY: "auto", borderRight: activeDeal ? "1px solid var(--border)" : "none", padding: "0 20px 20px" }}>
      <table className="tbl"><thead><tr><th>Status</th><th>Owner</th><th>Resort</th><th>Fee</th><th>Card Info</th><th>Closer</th><th>Admin</th><th>Date</th><th>Actions</th></tr></thead>
        <tbody>{filtered.map(d => (
          <tr key={d.id} onClick={() => setSelDeal(selDeal === d.id ? null : d.id)} style={{ cursor: "pointer", background: selDeal === d.id ? "var(--blue-s)" : "transparent" }}>
            <td><span className="tag" style={{ background: statusColor(d.status) + "22", color: statusColor(d.status) }}>{statusLabel(d.status)}</span></td>
            <td style={{ fontWeight: 600 }}>{d.ownerName}</td>
            <td style={{ fontSize: 11 }}>{d.resortName}</td>
            <td style={{ fontFamily: "'JetBrains Mono',monospace", fontWeight: 600, color: "var(--green)" }}>{fmt$(d.fee)}</td>
            <td style={{ fontSize: 10, fontFamily: "'JetBrains Mono',monospace" }}>{isAdmin ? `${d.cardType} ****${d.cardNumber ? d.cardNumber.slice(-4) : "----"}` : "****"}</td>
            <td>{users.find(u => u.id === d.closer)?.name || "-"}</td>
            <td>{users.find(u => u.id === d.assignedAdmin)?.name || "-"}</td>
            <td style={{ fontSize: 10, color: "var(--t3)", fontFamily: "'JetBrains Mono',monospace" }}>{d.chargedDate || d.timestamp || "-"}</td>
            <td style={{ whiteSpace: "nowrap" }} onClick={e => e.stopPropagation()}>
              {isAdmin && d.status === "pending_admin" && (
                <button className="btn btn-sm btn-p" onClick={() => updateStatus(d.id, "in_verification")}>Start Verification</button>
              )}
              {isAdmin && d.status === "in_verification" && (
                <div style={{ display: "flex", gap: 4, flexWrap: "wrap" }}>
                  <button className="btn btn-sm btn-g" onClick={() => updateStatus(d.id, "charged", { charged: "yes", chargedDate: todayStr() })}>Charge</button>
                  <button className="btn btn-sm" onClick={() => updateStatus(d.id, "cancelled", { charged: "no" })}>Cancel</button>
                  <button className="btn btn-sm btn-d" onClick={() => updateStatus(d.id, "pending_admin")}>Back</button>
                </div>
              )}
              {isAdmin && d.status === "charged" && (
                <div style={{ display: "flex", gap: 4, flexWrap: "wrap" }}>
                  <button className="btn btn-sm btn-d" onClick={() => updateStatus(d.id, "chargeback", { chargedBack: "yes" })}>Mark CB</button>
                  <button className="btn btn-sm" onClick={() => updateStatus(d.id, "cancelled", { charged: "no" })}>Cancel</button>
                </div>
              )}
              {isAdmin && d.status === "chargeback" && (
                <div style={{ display: "flex", gap: 4, flexWrap: "wrap" }}>
                  <button className="btn btn-sm" onClick={() => updateStatus(d.id, "charged", { chargedBack: "no" })}>Reverse CB</button>
                  <button className="btn btn-sm" onClick={() => updateStatus(d.id, "cancelled", { charged: "no", chargedBack: "no" })}>Cancel</button>
                </div>
              )}
              {isAdmin && d.status === "cancelled" && (
                <button className="btn btn-sm btn-p" onClick={() => updateStatus(d.id, "pending_admin", { charged: "no", chargedBack: "no" })}>Reactivate</button>
              )}
            </td>
          </tr>
        ))}{filtered.length === 0 && <tr><td colSpan={9} style={{ textAlign: "center", color: "var(--t3)", padding: 40 }}>No deals in this category</td></tr>}</tbody>
      </table>
      </div>
      {/* Detail / Notes panel */}
      {activeDeal && (
        <div style={{ flex: "0 0 45%", display: "flex", flexDirection: "column", overflow: "hidden" }}>
          <div style={{ padding: "14px 16px", borderBottom: "1px solid var(--border)", display: "flex", justifyContent: "space-between", alignItems: "flex-start", flexShrink: 0 }}>
            <div>
              <h3 style={{ fontSize: 15, fontWeight: 700 }}>{activeDeal.ownerName}</h3>
              <div style={{ fontSize: 11, color: "var(--t3)", marginTop: 2 }}>{activeDeal.resortName} | {fmt$(activeDeal.fee)} | <span style={{ color: statusColor(activeDeal.status) }}>{statusLabel(activeDeal.status)}</span></div>
            </div>
            <button className="btn btn-sm" onClick={() => setSelDeal(null)}>Close</button>
          </div>
          <div style={{ flex: 1, overflowY: "auto", padding: 14 }}>
            <div className="igrid c3" style={{ marginBottom: 12 }}>
              <div className="icard"><div className="lbl">Fee</div><div className="val" style={{ color: "var(--green)", fontWeight: 700 }}>{fmt$(activeDeal.fee)}</div></div>
              <div className="icard"><div className="lbl">Was VD</div><div className="val">{activeDeal.wasVD || "No"}</div></div>
              <div className="icard"><div className="lbl">Verification #</div><div className="val mono">{activeDeal.verificationNum || "-"}</div></div>
            </div>
            <div className="igrid" style={{ marginBottom: 12 }}>
              <div className="icard"><div className="lbl">Phone</div><div className="val mono">{activeDeal.primaryPhone || "-"}</div></div>
              <div className="icard"><div className="lbl">Email</div><div className="val mono" style={{ fontSize: 10 }}>{activeDeal.email || "-"}</div></div>
              <div className="icard"><div className="lbl">Address</div><div className="val">{activeDeal.mailingAddress} {activeDeal.cityStateZip}</div></div>
            </div>
            {isAdmin && (<>
              <div className="sec-title">Card Info</div>
              <div className="igrid" style={{ marginBottom: 12 }}>
                <div className="icard"><div className="lbl">Name on Card</div><div className="val">{activeDeal.nameOnCard || "-"}</div></div>
                <div className="icard"><div className="lbl">Card</div><div className="val mono">{activeDeal.cardType} {activeDeal.cardNumber || "-"}</div></div>
                <div className="icard"><div className="lbl">Exp / CV2</div><div className="val mono">{activeDeal.expDate || "-"} / {activeDeal.cv2 || "-"}</div></div>
                <div className="icard"><div className="lbl">Bank</div><div className="val">{activeDeal.bank || "-"}</div></div>
              </div>
            </>)}
            <div className="sec-title">Team</div>
            <div className="igrid c3" style={{ marginBottom: 12 }}>
              <div className="icard"><div className="lbl">Fronter</div><div className="val">{users.find(u => u.id === activeDeal.fronter)?.name || "Self"}</div></div>
              <div className="icard"><div className="lbl">Closer</div><div className="val">{users.find(u => u.id === activeDeal.closer)?.name || "-"}</div></div>
              <div className="icard"><div className="lbl">Admin</div><div className="val">{users.find(u => u.id === activeDeal.assignedAdmin)?.name || "-"}</div></div>
            </div>
            {activeDeal.loginInfo && (<><div className="sec-title">Login Info</div><div className="icard" style={{ marginBottom: 12 }}><div className="val mono" style={{ fontSize: 11, lineHeight: 1.6 }}>{activeDeal.loginInfo}</div></div></>)}
            {activeDeal.notes && (<><div className="sec-title">Deal Notes</div><div style={{ background: "var(--bg-2)", border: "1px solid var(--border)", borderRadius: "var(--r)", padding: 10, fontSize: 12, color: "var(--t2)", marginBottom: 12, lineHeight: 1.5 }}>{activeDeal.notes}</div></>)}
            <div className="sec-title">Notes & Comments</div>
            <div style={{ marginBottom: 12 }}>
              {(activeDeal.correspondence || []).length > 0 ? activeDeal.correspondence.map((c, i) => (
                <div key={i} style={{ padding: "8px 0", borderBottom: "1px solid var(--border)", fontSize: 12, color: "var(--t2)", lineHeight: 1.5 }}>{c}</div>
              )) : <div style={{ padding: 12, textAlign: "center", color: "var(--t3)", fontSize: 11 }}>No notes yet</div>}
            </div>
            <div style={{ padding: 12, background: "var(--bg-2)", border: "1px solid var(--border)", borderRadius: "var(--r)" }}>
              <div style={{ fontSize: 11, fontWeight: 600, marginBottom: 6, color: "var(--t1)" }}>Add Note / Comment</div>
              <div style={{ display: "flex", gap: 6 }}>
                <input value={noteInput} onChange={e => setNoteInput(e.target.value)} placeholder="Add details, notes, or comments..." style={{ flex: 1, background: "var(--bg-0)", border: "1px solid var(--border)", borderRadius: "var(--r-sm)", padding: "8px 12px", color: "var(--t1)", fontSize: 12, fontFamily: "inherit", outline: 0 }} onKeyDown={e => e.key === "Enter" && addVerifNote()} />
                <button className="btn btn-sm btn-p" onClick={addVerifNote} disabled={!noteInput.trim()}>Add</button>
              </div>
            </div>
          </div>
        </div>
      )}
    </div>
  </div>;
}

function PayrollView({ deals, users, currentUser, crmName }) {
  const [tab, setTab] = useState("my_pay");
  const [adminHours, setAdminHours] = useState({});
  const [sentSheets, setSentSheets] = useState({});
  const [adjustments, setAdjustments] = useState({});
  const [editingUser, setEditingUser] = useState(null);
  const [addDealForm, setAddDealForm] = useState(null);
  const [showRates, setShowRates] = useState(false);
  const [dbLoaded, setDbLoaded] = useState(false);
  const [saving, setSaving] = useState(false);
  const [historyTab, setHistoryTab] = useState(false);
  const [payrollHistory, setPayrollHistory] = useState([]);
  // Editable payroll rates (global defaults)
  const [rates, setRates] = useState({ closerPct: 50, fronterPct: 10, snrPct: 2, vdPct: 3, adminSnrPct: 2, hourlyRate: 19.50 });
  // Per-user rate overrides: { userId: { commPct: number, snrPct: number, hourlyRate: number } }
  const [userRates, setUserRates] = useState({});
  const getUserRate = (userId, key) => userRates[userId]?.[key];
  const setUserRate = (userId, key, val) => setUserRates(p => ({ ...p, [userId]: { ...(p[userId] || {}), [key]: val === "" ? undefined : Number(val) } }));
  const r = rates; // shorthand for global defaults
  const isMasterAdmin = currentUser.role === "master_admin";
  const chargedDeals = deals.filter(d => d.charged === "yes" && d.chargedBack !== "yes");
  const cbDeals = deals.filter(d => d.chargedBack === "yes");

  const now = new Date();
  const dayOfWeek = now.getDay();
  const monday = new Date(now); monday.setDate(now.getDate() - ((dayOfWeek + 6) % 7));
  const friday = new Date(monday); friday.setDate(monday.getDate() + 4);
  const weekLabel = monday.toLocaleDateString("en-US", { month: "short", day: "numeric" }) + " - " + friday.toLocaleDateString("en-US", { month: "short", day: "numeric", year: "numeric" });

  const weekStartStr = monday.toISOString().split('T')[0];

  // â”€â”€â”€ DATABASE PERSISTENCE â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
  // Load all payroll data from database on mount
  useEffect(() => {
    (async () => {
      try {
        const data = await PayrollAPI.loadWeekData(weekStartStr);
        if (data) {
          if (data.settings) {
            setRates({
              closerPct: Number(data.settings.closer_pct) || 50,
              fronterPct: Number(data.settings.fronter_pct) || 10,
              snrPct: Number(data.settings.snr_pct) || 2,
              vdPct: Number(data.settings.vd_pct) || 3,
              adminSnrPct: Number(data.settings.admin_snr_pct) || 2,
              hourlyRate: Number(data.settings.hourly_rate) || 19.50,
            });
          }
          if (data.userRates) {
            const ur = {};
            Object.entries(data.userRates).forEach(([uid, rv]) => {
              ur[uid] = {};
              if (rv.commPct !== null && rv.commPct !== undefined) ur[uid].commPct = rv.commPct;
              if (rv.snrPct !== null && rv.snrPct !== undefined) ur[uid].snrPct = rv.snrPct;
              if (rv.hourlyRate !== null && rv.hourlyRate !== undefined) ur[uid].hourlyRate = rv.hourlyRate;
            });
            setUserRates(ur);
          }
          if (data.adminHours) setAdminHours(data.adminHours);
          if (data.sentSheets) setSentSheets(data.sentSheets);
          // Rebuild adjustments from overrides, manual deals, notes
          if (data.dealOverrides || data.manualDeals || data.notes) {
            const adj = {};
            const initAdj = (uid) => { if (!adj[uid]) adj[uid] = { additions: [], deductions: [], removedDeals: [], addedDeals: [], priceOverrides: {}, nameOverrides: {}, dateOverrides: {}, vdOverrides: {}, commOverride: null, note: "" }; };
            if (data.dealOverrides) {
              Object.entries(data.dealOverrides).forEach(([uid, ovs]) => {
                initAdj(uid);
                ovs.forEach(ov => {
                  if (ov.type === 'price') adj[uid].priceOverrides[ov.dealId] = Number(ov.value);
                  else if (ov.type === 'name') adj[uid].nameOverrides[ov.dealId] = ov.value;
                  else if (ov.type === 'date') adj[uid].dateOverrides[ov.dealId] = ov.value;
                  else if (ov.type === 'vd') adj[uid].vdOverrides[ov.dealId] = ov.value;
                  else if (ov.type === 'remove') adj[uid].removedDeals.push(ov.dealId);
                });
              });
            }
            if (data.manualDeals) {
              Object.entries(data.manualDeals).forEach(([uid, mds]) => {
                initAdj(uid);
                adj[uid].addedDeals = mds.map(d => ({ dbId: d.id, name: d.name, amount: d.amount, date: d.date, vd: d.vd || "No" }));
              });
            }
            if (data.notes) {
              Object.entries(data.notes).forEach(([uid, note]) => { initAdj(uid); adj[uid].note = note; });
            }
            if (data.entries) {
              data.entries.forEach(entry => {
                initAdj(entry.user_id);
                try { if (entry.additions) adj[entry.user_id].additions = JSON.parse(entry.additions); } catch(e) {}
                try { if (entry.deductions) adj[entry.user_id].deductions = JSON.parse(entry.deductions); } catch(e) {}
              });
            }
            setAdjustments(adj);
          }
        }
      } catch (e) { console.error('[Payroll] Load error:', e); }
      setDbLoaded(true);
    })();
  }, [weekStartStr]);

  // Save rates to DB (debounced)
  const ratesTimer = useRef(null);
  useEffect(() => { if (!dbLoaded) return; clearTimeout(ratesTimer.current); ratesTimer.current = setTimeout(() => { PayrollAPI.saveSettings(rates, currentUser.id); }, 800); }, [rates, dbLoaded]);

  // Save user rates to DB (debounced)
  const urTimer = useRef(null);
  useEffect(() => { if (!dbLoaded) return; clearTimeout(urTimer.current); urTimer.current = setTimeout(() => { Object.entries(userRates).forEach(([uid, rv]) => { PayrollAPI.saveUserRate(uid, rv.commPct ?? null, rv.snrPct ?? null, rv.hourlyRate ?? null); }); }, 800); }, [userRates, dbLoaded]);

  // DB-backed sendPaysheet
  const sendPaysheet = async (userId, amount) => {
    const u = users.find(x => x.id === userId);
    setSentSheets(p => ({ ...p, [userId]: { sentAt: nowT(), amount, sentBy: currentUser.name } }));
    await PayrollAPI.sendPaysheet(userId, u?.name || '', u?.role || '', weekStartStr, weekLabel, amount, currentUser.name, null);
  };
  const sendAll = async (payList) => {
    const u = {};
    for (const p of payList) { const fp = getFinalPay(p); u[p.id] = { sentAt: nowT(), amount: fp, sentBy: currentUser.name }; await PayrollAPI.sendPaysheet(p.id, p.name, p.role, weekStartStr, weekLabel, fp, currentUser.name, null); }
    setSentSheets(prev => ({ ...prev, ...u }));
  };

  // Adjustments with DB persistence
  const getAdj = (userId) => adjustments[userId] || { additions: [], deductions: [], removedDeals: [], addedDeals: [], priceOverrides: {}, nameOverrides: {}, dateOverrides: {}, vdOverrides: {}, commOverride: null, note: "" };
  const setAdj = (userId, adj) => setAdjustments(p => ({ ...p, [userId]: adj }));
  const addAddition = (userId, desc, amount) => { const a = getAdj(userId); setAdj(userId, { ...a, additions: [...a.additions, { desc, amount: Number(amount) }] }); };
  const addDeduction = (userId, desc, amount) => { const a = getAdj(userId); setAdj(userId, { ...a, deductions: [...a.deductions, { desc, amount: Number(amount) }] }); };
  const removeDeal = (userId, dealId) => { const a = getAdj(userId); setAdj(userId, { ...a, removedDeals: [...a.removedDeals, dealId] }); PayrollAPI.saveDealOverride(userId, dealId, weekStartStr, 'remove', 'true'); };
  const undoRemoveDeal = (userId, dealId) => { const a = getAdj(userId); setAdj(userId, { ...a, removedDeals: a.removedDeals.filter(x => x !== dealId) }); PayrollAPI.undoDealOverride(userId, dealId, weekStartStr, 'remove'); };
  const addManualDeal = async (userId, name, amount, date, vd) => {
    const res = await PayrollAPI.addManualDeal(userId, weekStartStr, name, amount, date, vd || "No", currentUser.id);
    const a = getAdj(userId);
    setAdj(userId, { ...a, addedDeals: [...a.addedDeals, { dbId: res?.id, name, amount: Number(amount), date, vd: vd || "No" }] });
  };
  const removeAddition = (userId, i) => { const a = getAdj(userId); setAdj(userId, { ...a, additions: a.additions.filter((_, j) => j !== i) }); };
  const removeDeduction = (userId, i) => { const a = getAdj(userId); setAdj(userId, { ...a, deductions: a.deductions.filter((_, j) => j !== i) }); };
  const setNote = (userId, note) => { const a = getAdj(userId); setAdj(userId, { ...a, note }); PayrollAPI.saveNote(userId, weekStartStr, note, currentUser.id); };
  const setPriceOverride = (userId, dealId, amt) => { const a = getAdj(userId); setAdj(userId, { ...a, priceOverrides: { ...a.priceOverrides, [dealId]: Number(amt) } }); PayrollAPI.saveDealOverride(userId, dealId, weekStartStr, 'price', String(amt)); };
  const setNameOverride = (userId, dealId, name) => { const a = getAdj(userId); setAdj(userId, { ...a, nameOverrides: { ...a.nameOverrides, [dealId]: name } }); PayrollAPI.saveDealOverride(userId, dealId, weekStartStr, 'name', name); };
  const setDateOverride = (userId, dealId, date) => { const a = getAdj(userId); setAdj(userId, { ...a, dateOverrides: { ...a.dateOverrides, [dealId]: date } }); PayrollAPI.saveDealOverride(userId, dealId, weekStartStr, 'date', date); };
  const setVdOverride = (userId, dealId, vd) => { const a = getAdj(userId); setAdj(userId, { ...a, vdOverrides: { ...a.vdOverrides, [dealId]: vd } }); PayrollAPI.saveDealOverride(userId, dealId, weekStartStr, 'vd', vd); };
  const setCommOverride = (userId, val) => { const a = getAdj(userId); setAdj(userId, { ...a, commOverride: val === "" ? null : Number(val) }); };
  const editManualDeal = (userId, idx, field, val) => {
    const a = getAdj(userId); const d = [...a.addedDeals]; d[idx] = { ...d[idx], [field]: field === "amount" ? Number(val) : val }; setAdj(userId, { ...a, addedDeals: d });
    const deal = d[idx]; if (deal.dbId) PayrollAPI.updateManualDeal(deal.dbId, deal.name, deal.amount, deal.date, deal.vd);
  };
  const removeManualDeal = (userId, idx) => {
    const a = getAdj(userId); const deal = a.addedDeals[idx];
    if (deal?.dbId) PayrollAPI.removeManualDeal(deal.dbId);
    setAdj(userId, { ...a, addedDeals: a.addedDeals.filter((_, j) => j !== idx) });
  };

  // Save admin hours to DB
  const saveAdminHoursDb = (userId, hours) => {
    setAdminHours(prev => ({ ...prev, [userId]: hours }));
    PayrollAPI.saveAdminHours(userId, hours, weekStartStr);
  };

  // Load payroll history
  const loadHistory = async () => {
    const res = await PayrollAPI.getHistory(null, 100);
    if (res?.history) setPayrollHistory(res.history);
    setHistoryTab(true);
  };

  // Export CSV
  const exportCSV = () => { window.open(PayrollAPI.getExportCSVUrl(weekStartStr), '_blank'); };

  const calcPay = (u) => {
    const adj = getAdj(u.id);
    const ur = userRates[u.id] || {};
    const myCloserPct = (ur.commPct !== undefined ? ur.commPct : r.closerPct) / 100;
    const myFronterPct = (ur.commPct !== undefined && u.role === "fronter" ? ur.commPct : r.fronterPct) / 100;
    const mySnrPct = (ur.snrPct !== undefined ? ur.snrPct : r.snrPct) / 100;
    const myAdminPct = (ur.commPct !== undefined && (u.role === "admin" || u.role === "admin_limited") ? ur.commPct : r.adminSnrPct) / 100;
    const myHourly = ur.hourlyRate !== undefined ? ur.hourlyRate : r.hourlyRate;
    const myVdPct = r.vdPct / 100;
    const vdMult = 1 - myVdPct;
    const ov = (d) => {
      const fee = adj.priceOverrides?.[d.id] !== undefined ? adj.priceOverrides[d.id] : (Number(d.fee)||0);
      const nm = adj.nameOverrides?.[d.id] || d.ownerName;
      const dt = adj.dateOverrides?.[d.id] || d.timestamp;
      const vd = adj.vdOverrides?.[d.id] !== undefined ? adj.vdOverrides[d.id] : d.wasVD;
      return { ...d, fee, ownerName: nm, timestamp: dt, wasVD: vd, payoutAmt: vd === "Yes" ? fee * vdMult : fee };
    };
    const mkM = (d, i) => ({ id:"manual_"+i, ownerName:d.name, fee:d.amount, timestamp:d.date, wasVD:d.vd||"No", payoutAmt:(d.vd||"No")==="Yes"?d.amount*vdMult:d.amount, fronter:null, isManual:true, manualIdx:i });
    if (u.role === "closer") {
      const myD = chargedDeals.filter(d => d.closer === u.id && !adj.removedDeals?.includes(d.id)).map(ov);
      const allD = [...myD, ...adj.addedDeals.map(mkM)];
      const myCB = cbDeals.filter(d => d.closer === u.id);
      const ts = allD.reduce((s,d) => s+(Number(d.fee)||0), 0), tp = allD.reduce((s,d) => s+d.payoutAmt, 0);
      const cbt = myCB.reduce((s,d) => s+(Number(d.fee)||0), 0);
      const fp = tp*myCloserPct, fc = allD.reduce((s,d) => s+(d.fronter?d.payoutAmt*(r.fronterPct/100):0), 0), snr = tp*mySnrPct;
      return { ...u, type:"closer", deals:allD, totalSold:ts, totalPayout:tp, vdTaken:ts-tp, fiftyPct:fp, fronterCut:fc, snr, grossPay:fp-fc-snr, cbTotal:cbt, netPay:fp-fc-snr-(cbt*myCloserPct), dealCount:allD.length, cbCount:myCB.length, vdCount:allD.filter(d=>d.wasVD==="Yes").length, myPct:myCloserPct*100 };
    } else if (u.role === "fronter") {
      const myD = chargedDeals.filter(d => d.fronter === u.id && !adj.removedDeals?.includes(d.id)).map(ov);
      const allD = [...myD, ...adj.addedDeals.map(mkM)];
      const myCB = cbDeals.filter(d => d.fronter === u.id);
      const ts = allD.reduce((s,d) => s+(Number(d.fee)||0), 0), tp = allD.reduce((s,d) => s+d.payoutAmt, 0);
      const cbt = myCB.reduce((s,d) => s+(Number(d.fee)||0), 0), tenP = tp*myFronterPct;
      return { ...u, type:"fronter", deals:allD, totalSold:ts, totalPayout:tp, vdTaken:ts-tp, tenPct:tenP, grossPay:tenP, cbTotal:cbt, netPay:tenP-(cbt*myFronterPct), dealCount:allD.length, cbCount:myCB.length, vdCount:allD.filter(d=>d.wasVD==="Yes").length, myPct:myFronterPct*100 };
    } else {
      const myD = chargedDeals.filter(d => d.assignedAdmin === u.id && !adj.removedDeals?.includes(d.id)).map(ov);
      const allD = [...myD, ...adj.addedDeals.map(mkM)];
      const myCB = cbDeals.filter(d => d.assignedAdmin === u.id);
      const ts = allD.reduce((s,d) => s+(Number(d.fee)||0), 0), tp = allD.reduce((s,d) => s+d.payoutAmt, 0);
      const cbt = myCB.reduce((s,d) => s+(Number(d.fee)||0), 0);
      const cp = tp*myAdminPct, hrs = adminHours[u.id]||0, hp = hrs*myHourly;
      return { ...u, type:"admin", deals:allD, totalSold:ts, totalPayout:tp, vdTaken:ts-tp, commissionPct:cp, hours:hrs, hourlyPay:hp, grossPay:cp+hp, cbTotal:cbt, netPay:cp+hp-(cbt*myAdminPct), dealCount:allD.length, cbCount:myCB.length, vdCount:allD.filter(d=>d.wasVD==="Yes").length, myPct:myAdminPct*100, myHourly };
    }
  };

  const getFinalPay = (p) => {
    const adj = getAdj(p.id);
    const addTotal = adj.additions.reduce((s,a) => s + a.amount, 0);
    const dedTotal = adj.deductions.reduce((s,a) => s + a.amount, 0);
    return Math.max(0, p.netPay + addTotal - dedTotal);
  };

  const myPay = calcPay(currentUser);
  const allClosers = users.filter(u => u.role === "closer").map(calcPay);
  const allFronters = users.filter(u => u.role === "fronter").map(calcPay);
  const allAdmins = users.filter(u => u.role === "admin" || u.role === "admin_limited").map(calcPay);

  // PDF / Print
  const printPaysheet = (p) => {
    const adj = getAdj(p.id);
    const finalPay = getFinalPay(p);
    const html = `<html><head><style>body{font-family:Arial,sans-serif;padding:40px;color:#111}h1{font-size:24px;margin-bottom:4px}h2{font-size:16px;color:#666;margin-bottom:20px}.meta{font-size:12px;color:#999;margin-bottom:20px}table{width:100%;border-collapse:collapse;margin-bottom:16px}th,td{padding:8px 12px;border:1px solid #ddd;text-align:left;font-size:13px}th{background:#f5f5f5;font-weight:600}.r{text-align:right}.g{color:#16a34a}.rd{color:#dc2626}.total{font-size:18px;font-weight:700}@media print{body{padding:20px}}</style></head><body><h1>${crmName || "PRIME CRM"} - Paysheet</h1><h2>${p.name} (${ROLE_LABELS[p.role]})</h2><div class="meta">Week: ${weekLabel} | Generated: ${nowT()}</div><table><tr><th>Description</th><th class="r">Amount</th></tr><tr><td>Total Sold</td><td class="r">${fmt$(p.totalSold)}</td></tr>${p.vdTaken > 0 ? `<tr><td>VD -${r.vdPct}% (${p.vdCount} deals)</td><td class="r rd">-${fmt$(p.vdTaken)}</td></tr>` : ""}<tr><td>Payout Amount</td><td class="r">${fmt$(p.totalPayout)}</td></tr>${p.type === "closer" ? `<tr><td>${r.closerPct}% Commission</td><td class="r g">${fmt$(p.fiftyPct)}</td></tr><tr><td>Fronter ${r.fronterPct}% Cut</td><td class="r rd">-${fmt$(p.fronterCut)}</td></tr><tr><td>SNR ${r.snrPct}% (Admin)</td><td class="r rd">-${fmt$(p.snr)}</td></tr>` : ""}${p.type === "fronter" ? `<tr><td>${r.fronterPct}% Commission</td><td class="r g">${fmt$(p.tenPct)}</td></tr>` : ""}${p.type === "admin" ? `<tr><td>${r.adminSnrPct}% SNR Commission</td><td class="r g">${fmt$(p.commissionPct)}</td></tr><tr><td>Hourly (${p.hours || 0}hrs x $${r.hourlyRate})</td><td class="r g">+${fmt$(p.hourlyPay || 0)}</td></tr>` : ""}${p.cbCount > 0 ? `<tr><td>CB/Refunds (${p.cbCount})</td><td class="r rd">-${fmt$(p.cbTotal * (p.type === "closer" ? r.closerPct/100 : p.type === "fronter" ? r.fronterPct/100 : r.adminSnrPct/100))}</td></tr>` : ""}${adj.additions.map(a => `<tr><td>+ ${a.desc}</td><td class="r g">+${fmt$(a.amount)}</td></tr>`).join("")}${adj.deductions.map(a => `<tr><td>- ${a.desc}</td><td class="r rd">-${fmt$(a.amount)}</td></tr>`).join("")}<tr style="border-top:2px solid #111"><td class="total">NET PAY</td><td class="r total g">${fmt$(finalPay)}</td></tr></table>${adj.note ? `<div style="margin-top:12px;padding:12px;background:#f9f9f9;border:1px solid #ddd;border-radius:6px;font-size:12px"><strong>Notes:</strong> ${adj.note}</div>` : ""}<h3 style="margin-top:20px;font-size:14px">Deal Breakdown</h3><table><tr><th>Customer</th><th>Date</th><th class="r">Amount</th><th>VD</th></tr>${p.deals.map(d => `<tr><td>${d.ownerName}${d.isManual ? " (manual)" : ""}</td><td>${d.timestamp || "-"}</td><td class="r">${fmt$(d.fee)}</td><td>${d.wasVD === "Yes" ? "Yes" : "-"}</td></tr>`).join("")}</table></body></html>`;
    const w = window.open("", "_blank");
    w.document.write(html);
    w.document.close();
    w.print();
  };

  const PayCard = ({ label, value, color }) => (
    <div style={{ textAlign: "center", padding: "8px 0" }}>
      <div style={{ fontSize: 18, fontWeight: 700, fontFamily: "'JetBrains Mono',monospace", color: color || "var(--t1)" }}>{value}</div>
      <div style={{ fontSize: 9, color: "var(--t3)", textTransform: "uppercase" }}>{label}</div>
    </div>
  );

  const renderPayCard = (p, showSend = false) => {
    const adj = getAdj(p.id);
    const addTotal = adj.additions.reduce((s,a) => s + a.amount, 0);
    const dedTotal = adj.deductions.reduce((s,a) => s + a.amount, 0);
    const finalPay = getFinalPay(p);
    return (
    <div key={p.id} className="icard fin" style={{ marginBottom: 14 }} id={"pay-" + p.id}>
      <div style={{ display: "flex", alignItems: "center", gap: 12, marginBottom: 12, paddingBottom: 12, borderBottom: "1px solid var(--border)" }}>
        <div style={{ width: 36, height: 36, borderRadius: "50%", background: p.color, display: "flex", alignItems: "center", justifyContent: "center", fontSize: 12, fontWeight: 600, color: "#fff" }}>{p.avatar}</div>
        <div style={{ flex: 1 }}>
          <div style={{ fontSize: 15, fontWeight: 700 }}>{p.name}</div>
          <div style={{ fontSize: 11, color: "var(--t3)" }}>{ROLE_LABELS[p.role]} | {p.dealCount} deals | Week: {weekLabel}</div>
          {isMasterAdmin && (
            <div style={{ display: "flex", gap: 8, marginTop: 6, alignItems: "center" }}>
              <label style={{ fontSize: 9, color: "var(--t3)" }}>Comm %:</label>
              <input type="number" step="0.5" value={getUserRate(p.id, "commPct") ?? (p.type === "closer" ? r.closerPct : p.type === "fronter" ? r.fronterPct : r.adminSnrPct)} onChange={e => setUserRate(p.id, "commPct", e.target.value)} style={{ width: 55, background: "var(--bg-3)", border: "1px solid var(--border)", borderRadius: 3, padding: "2px 6px", color: "var(--t1)", fontSize: 12, fontFamily: "'JetBrains Mono',monospace", fontWeight: 600, outline: 0, textAlign: "center" }} />
              {p.type !== "closer" && p.type !== "fronter" && (<>
                <label style={{ fontSize: 9, color: "var(--t3)" }}>$/hr:</label>
                <input type="number" step="0.50" value={getUserRate(p.id, "hourlyRate") ?? r.hourlyRate} onChange={e => setUserRate(p.id, "hourlyRate", e.target.value)} style={{ width: 60, background: "var(--bg-3)", border: "1px solid var(--border)", borderRadius: 3, padding: "2px 6px", color: "var(--t1)", fontSize: 12, fontFamily: "'JetBrains Mono',monospace", fontWeight: 600, outline: 0, textAlign: "center" }} />
              </>)}
              {p.type === "closer" && (<>
                <label style={{ fontSize: 9, color: "var(--t3)" }}>SNR %:</label>
                <input type="number" step="0.5" value={getUserRate(p.id, "snrPct") ?? r.snrPct} onChange={e => setUserRate(p.id, "snrPct", e.target.value)} style={{ width: 50, background: "var(--bg-3)", border: "1px solid var(--border)", borderRadius: 3, padding: "2px 6px", color: "var(--t1)", fontSize: 12, fontFamily: "'JetBrains Mono',monospace", fontWeight: 600, outline: 0, textAlign: "center" }} />
              </>)}
              {(getUserRate(p.id, "commPct") !== undefined || getUserRate(p.id, "hourlyRate") !== undefined || getUserRate(p.id, "snrPct") !== undefined) && <span style={{ fontSize: 9, color: "var(--amber)", cursor: "pointer" }} onClick={() => setUserRates(prev => { const n = { ...prev }; delete n[p.id]; return n; })}>reset</span>}
            </div>
          )}
        </div>
        <div style={{ textAlign: "right", marginRight: 8 }}><div style={{ fontSize: 28, fontWeight: 800, fontFamily: "'JetBrains Mono',monospace", color: finalPay >= 0 ? "var(--green)" : "var(--red)" }}>{fmt$(finalPay)}</div><div style={{ fontSize: 10, color: "var(--t3)" }}>FINAL PAY</div></div>
        <div style={{ display: "flex", flexDirection: "column", gap: 4, alignItems: "flex-end" }}>
          {isMasterAdmin && <button className="btn btn-sm" onClick={() => printPaysheet(p)}>ðŸ–¨ PDF/Print</button>}
          {showSend && !sentSheets[p.id] && <button className="btn btn-sm btn-g" onClick={() => sendPaysheet(p.id, finalPay)}>Send</button>}
          {sentSheets[p.id] && <span className="tag" style={{ background: "var(--green-s)", color: "var(--green)" }}>SENT</span>}
        </div>
      </div>
      {p.type === "admin" && isMasterAdmin && (
        <div style={{ display: "flex", gap: 8, marginBottom: 10, alignItems: "center" }}>
          <label style={{ fontSize: 11, color: "var(--t3)" }}>Hours:</label>
          <input type="number" value={adminHours[p.id] || ""} onChange={e => saveAdminHoursDb(p.id, Number(e.target.value) || 0)} placeholder="0" style={{ width: 70, background: "var(--bg-3)", border: "1px solid var(--border)", borderRadius: "var(--r-sm)", padding: "4px 8px", color: "var(--t1)", fontSize: 12, fontFamily: "inherit", outline: 0 }} />
          <span style={{ fontSize: 11, color: "var(--t3)" }}>x ${r.hourlyRate}/hr = <strong style={{ color: "var(--blue)" }}>{fmt$(p.hourlyPay)}</strong></span>
        </div>
      )}
      {p.type === "closer" && <div style={{ display: "grid", gridTemplateColumns: "repeat(7,1fr)", gap: 8 }}><PayCard label="Total Sold" value={fmt$(p.totalSold)} /><PayCard label={"VD -"+r.vdPct+"% ("+p.vdCount+")"} value={p.vdTaken>0?"-"+fmt$(p.vdTaken):"$0"} color="var(--purple)" /><PayCard label="Payout Amt" value={fmt$(p.totalPayout)} /><PayCard label={(p.myPct||r.closerPct)+"% Comm"} value={fmt$(p.fiftyPct)} color="var(--blue)" /><PayCard label={"Fronter "+r.fronterPct+"%"} value={"-"+fmt$(p.fronterCut)} color="var(--pink)" /><PayCard label={"SNR "+(getUserRate(p.id,"snrPct")??r.snrPct)+"% (Admin)"} value={"-"+fmt$(p.snr)} color="var(--amber)" /><PayCard label="CB/Refunds" value={p.cbCount>0?"-"+fmt$(p.cbTotal*(p.myPct/100)):"$0"} color="var(--red)" /></div>}
      {p.type === "fronter" && <div style={{ display: "grid", gridTemplateColumns: "repeat(5,1fr)", gap: 8 }}><PayCard label="Total Sold" value={fmt$(p.totalSold)} /><PayCard label={"VD -"+r.vdPct+"% ("+p.vdCount+")"} value={p.vdTaken>0?"-"+fmt$(p.vdTaken):"$0"} color="var(--purple)" /><PayCard label="Payout Amt" value={fmt$(p.totalPayout)} /><PayCard label={(p.myPct||r.fronterPct)+"% Comm"} value={fmt$(p.tenPct)} color="var(--blue)" /><PayCard label="CB/Refunds" value={p.cbCount>0?"-"+fmt$(p.cbTotal*(p.myPct/100)):"$0"} color="var(--red)" /></div>}
      {p.type === "admin" && <div style={{ display: "grid", gridTemplateColumns: "repeat(6,1fr)", gap: 6 }}><PayCard label="Total Sold" value={fmt$(p.totalSold)} /><PayCard label={"VD -"+r.vdPct+"% ("+(p.vdCount||0)+")"} value={p.vdTaken>0?"-"+fmt$(p.vdTaken):"$0"} color="var(--purple)" /><PayCard label="Payout Amt" value={fmt$(p.totalPayout)} /><PayCard label={(p.myPct||r.adminSnrPct)+"% SNR"} value={fmt$(p.commissionPct)} color="var(--blue)" /><PayCard label={"$"+(p.myHourly||r.hourlyRate)+"/hr"} value={"+"+fmt$(p.hourlyPay||0)} color="var(--blue)" /><PayCard label="CB/Refunds" value={p.cbCount>0?"-"+fmt$(p.cbTotal*(p.myPct/100)):"$0"} color="var(--red)" /></div>}
      {/* Adjustments display */}
      {(adj.additions.length > 0 || adj.deductions.length > 0) && (
        <div style={{ marginTop: 10, padding: 10, background: "var(--bg-2)", borderRadius: "var(--r)", border: "1px solid var(--border)" }}>
          <div style={{ fontSize: 11, fontWeight: 600, color: "var(--t1)", marginBottom: 6 }}>Adjustments</div>
          {adj.additions.map((a, i) => <div key={"a"+i} style={{ display: "flex", justifyContent: "space-between", padding: "3px 0", fontSize: 11 }}><span style={{ color: "var(--green)" }}>+ {a.desc}</span><div style={{ display: "flex", gap: 8 }}><span style={{ fontFamily: "'JetBrains Mono',monospace", color: "var(--green)" }}>+{fmt$(a.amount)}</span>{isMasterAdmin && <span style={{ cursor: "pointer", color: "var(--red)", fontSize: 10 }} onClick={() => removeAddition(p.id, i)}>remove</span>}</div></div>)}
          {adj.deductions.map((a, i) => <div key={"d"+i} style={{ display: "flex", justifyContent: "space-between", padding: "3px 0", fontSize: 11 }}><span style={{ color: "var(--red)" }}>- {a.desc}</span><div style={{ display: "flex", gap: 8 }}><span style={{ fontFamily: "'JetBrains Mono',monospace", color: "var(--red)" }}>-{fmt$(a.amount)}</span>{isMasterAdmin && <span style={{ cursor: "pointer", color: "var(--red)", fontSize: 10 }} onClick={() => removeDeduction(p.id, i)}>remove</span>}</div></div>)}
        </div>
      )}
      {adj.note && <div style={{ marginTop: 6, padding: 8, background: "var(--amber-s)", borderRadius: "var(--r-sm)", fontSize: 11, color: "var(--amber)" }}>Note: {adj.note}</div>}
      {/* Deals list */}
      {p.deals.length > 0 && (
        <div style={{ marginTop: 10, fontSize: 11 }}>
          <div style={{ display: "grid", gridTemplateColumns: "1fr 100px 90px 40px 50px", gap: 4, padding: "4px 0", borderBottom: "2px solid var(--border)", fontWeight: 600, color: "var(--t3)", fontSize: 10 }}>
            <span>CUSTOMER</span><span>DATE</span><span style={{ textAlign: "right" }}>AMOUNT</span><span>VD</span><span></span>
          </div>
          {p.deals.map((d, i) => (
            <div key={d.id || i} style={{ display: "grid", gridTemplateColumns: "1fr 100px 90px 40px 50px", gap: 4, alignItems: "center", padding: "4px 0", borderBottom: "1px solid var(--border)" }}>
              {isMasterAdmin && !d.isManual ? (
                <input value={d.ownerName} onChange={e => setNameOverride(p.id, d.id, e.target.value)} style={{ background: "transparent", border: "1px solid transparent", borderRadius: 3, padding: "2px 4px", color: "var(--t1)", fontSize: 11, fontFamily: "inherit", outline: 0 }} onFocus={e => e.target.style.borderColor = "var(--border-h)"} onBlur={e => e.target.style.borderColor = "transparent"} />
              ) : (
                <span>{d.ownerName}{d.isManual && <span style={{ color: "var(--blue)", marginLeft: 4, fontSize: 9 }}>(added)</span>}</span>
              )}
              {isMasterAdmin && !d.isManual ? (
                <input value={d.timestamp || ""} onChange={e => setDateOverride(p.id, d.id, e.target.value)} style={{ background: "transparent", border: "1px solid transparent", borderRadius: 3, padding: "2px 4px", color: "var(--t3)", fontSize: 11, fontFamily: "inherit", outline: 0, width: 90 }} onFocus={e => e.target.style.borderColor = "var(--border-h)"} onBlur={e => e.target.style.borderColor = "transparent"} />
              ) : (
                <span style={{ color: "var(--t3)" }}>{d.timestamp}</span>
              )}
              {isMasterAdmin && !d.isManual ? (
                <input type="number" value={d.fee} onChange={e => setPriceOverride(p.id, d.id, e.target.value)} style={{ background: "transparent", border: "1px solid transparent", borderRadius: 3, padding: "2px 4px", color: "var(--green)", fontSize: 11, fontFamily: "'JetBrains Mono',monospace", fontWeight: 600, outline: 0, textAlign: "right", width: 80 }} onFocus={e => e.target.style.borderColor = "var(--border-h)"} onBlur={e => e.target.style.borderColor = "transparent"} />
              ) : isMasterAdmin && d.isManual ? (
                <input type="number" value={d.fee} onChange={e => editManualDeal(p.id, d.manualIdx, "amount", e.target.value)} style={{ background: "transparent", border: "1px solid transparent", borderRadius: 3, padding: "2px 4px", color: "var(--green)", fontSize: 11, fontFamily: "'JetBrains Mono',monospace", fontWeight: 600, outline: 0, textAlign: "right", width: 80 }} onFocus={e => e.target.style.borderColor = "var(--border-h)"} onBlur={e => e.target.style.borderColor = "transparent"} />
              ) : (
                <span style={{ fontFamily: "'JetBrains Mono',monospace", fontWeight: 600, color: "var(--green)", textAlign: "right" }}>{fmt$(d.fee)}</span>
              )}
              {isMasterAdmin ? (
                <span style={{ cursor: "pointer", fontSize: 10 }} onClick={() => d.isManual ? editManualDeal(p.id, d.manualIdx, "vd", d.wasVD === "Yes" ? "No" : "Yes") : setVdOverride(p.id, d.id, d.wasVD === "Yes" ? "No" : "Yes")}><span className="tag" style={{ background: d.wasVD === "Yes" ? "var(--purple-s)" : "var(--bg-3)", color: d.wasVD === "Yes" ? "var(--purple)" : "var(--t3)" }}>{d.wasVD === "Yes" ? "VD" : "-"}</span></span>
              ) : (
                d.wasVD === "Yes" && <span className="tag" style={{ background: "var(--purple-s)", color: "var(--purple)" }}>VD</span>
              )}
              <div style={{ display: "flex", gap: 4 }}>
                {isMasterAdmin && !d.isManual && <span style={{ cursor: "pointer", color: "var(--red)", fontSize: 9 }} onClick={() => removeDeal(p.id, d.id)}>X</span>}
                {isMasterAdmin && d.isManual && <span style={{ cursor: "pointer", color: "var(--red)", fontSize: 9 }} onClick={() => removeManualDeal(p.id, d.manualIdx)}>X</span>}
              </div>
            </div>
          ))}
          {/* Show removed deals */}
          {adj.removedDeals.length > 0 && adj.removedDeals.map(dId => {
            const d = deals.find(x => x.id === dId);
            return d ? <div key={dId} style={{ display: "flex", justifyContent: "space-between", alignItems: "center", padding: "4px 0", borderBottom: "1px solid var(--border)", opacity: 0.4, textDecoration: "line-through" }}><span>{d.ownerName}</span><span style={{ fontFamily: "'JetBrains Mono',monospace" }}>{fmt$(d.fee)}</span>{isMasterAdmin && <span style={{ cursor: "pointer", color: "var(--blue)", fontSize: 10, textDecoration: "none" }} onClick={() => undoRemoveDeal(p.id, dId)}>undo</span>}</div> : null;
          })}
        </div>
      )}
      {/* Master admin edit tools */}
      {isMasterAdmin && (
        <div style={{ marginTop: 12, padding: 12, background: "var(--bg-2)", borderRadius: "var(--r)", border: "1px dashed var(--border)" }}>
          <div style={{ fontSize: 11, fontWeight: 600, marginBottom: 8, color: "var(--t1)" }}>Master Admin Corrections</div>
          <div style={{ display: "flex", gap: 6, flexWrap: "wrap", marginBottom: 8 }}>
            <button className="btn btn-sm btn-g" onClick={() => { const desc = prompt("Addition description:"); const amt = prompt("Amount ($):"); if (desc && amt) addAddition(p.id, desc, amt); }}>+ Add Bonus</button>
            <button className="btn btn-sm btn-d" onClick={() => { const desc = prompt("Deduction description:"); const amt = prompt("Amount ($):"); if (desc && amt) addDeduction(p.id, desc, amt); }}>- Deduction</button>
            <button className="btn btn-sm" onClick={() => { const nm = prompt("Customer name:"); const amt = prompt("Deal amount ($):"); const dt = prompt("Date:"); if (nm && amt) addManualDeal(p.id, nm, amt, dt || todayStr()); }}>+ Add Deal</button>
            <button className="btn btn-sm" onClick={() => { const n = prompt("Paysheet note:", adj.note); if (n !== null) setNote(p.id, n); }}>Add Note</button>
          </div>
        </div>
      )}
    </div>
  );};

  if (!isMasterAdmin) {
    return (
      <div style={{ flex: 1, display: "flex", flexDirection: "column", overflow: "hidden" }}>
        <div style={{ padding: "16px 20px", borderBottom: "1px solid var(--border)", flexShrink: 0 }}>
          <h2 style={{ fontSize: 16, fontWeight: 700 }}>My Paysheet</h2>
          <p style={{ fontSize: 12, color: "var(--t3)", marginTop: 2 }}>Week of {weekLabel}</p>
        </div>
        <div style={{ flex: 1, overflowY: "auto", padding: 20 }}>
          {renderPayCard(myPay, false)}
          {sentSheets[currentUser.id] && <div style={{ padding: 12, background: "var(--green-s)", border: "1px solid rgba(16,185,129,.3)", borderRadius: "var(--r)", fontSize: 12, color: "var(--green)" }}>Paysheet sent by {sentSheets[currentUser.id].sentBy} on {sentSheets[currentUser.id].sentAt}</div>}
        </div>
      </div>
    );
  }

  return (
    <div style={{ flex: 1, display: "flex", flexDirection: "column", overflow: "hidden" }}>
      <div className="tabs">
        {[["closers","Closers ("+allClosers.length+")"],["fronters","Fronters ("+allFronters.length+")"],["admins","Admins ("+allAdmins.length+")"],["sent","Sent History"],["history","Payroll History"]].map(([k,l]) => (
          <div key={k} className={`tab ${tab===k?"on":""}`} onClick={() => setTab(k)}>{l}</div>
        ))}
      </div>
      <div style={{ flex: 1, overflowY: "auto", padding: 20 }}>
        <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center", padding: "10px 14px", background: "var(--blue-s)", border: "1px solid rgba(59,130,246,.2)", borderRadius: "var(--r)", marginBottom: 16 }}>
          <div><div style={{ fontSize: 13, fontWeight: 600, color: "var(--t1)" }}>Week of {weekLabel}</div><div style={{ fontSize: 11, color: "var(--t3)" }}>Paysheets sent every Friday morning</div></div>
          <div style={{ display: "flex", gap: 6 }}>
            {tab !== "sent" && tab !== "history" && <button className="btn btn-sm btn-p" onClick={() => { sendAll(tab==="closers"?allClosers:tab==="fronters"?allFronters:allAdmins); }}>Send All</button>}
            <button className="btn btn-sm" onClick={exportCSV}>ðŸ“¥ Export CSV</button>
          </div>
        </div>
        {/* Payroll Rate Settings */}
        <div style={{ marginBottom: 16 }}>
          <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center", cursor: "pointer" }} onClick={() => setShowRates(!showRates)}>
            <span style={{ fontSize: 12, fontWeight: 600, color: "var(--t2)" }}>Payroll Rates Settings</span>
            <span style={{ fontSize: 10, color: "var(--t3)" }}>{showRates ? "Hide" : "Edit Rates"}</span>
          </div>
          {showRates && (
            <div style={{ marginTop: 10, padding: 14, background: "var(--bg-2)", border: "1px solid var(--border)", borderRadius: "var(--r)", display: "grid", gridTemplateColumns: "repeat(3,1fr)", gap: 12 }}>
              {[
                ["closerPct", "Closer Commission %", r.closerPct],
                ["fronterPct", "Fronter Commission %", r.fronterPct],
                ["snrPct", "SNR % (Closer -> Admin)", r.snrPct],
                ["vdPct", "VD Deduction %", r.vdPct],
                ["adminSnrPct", "Admin SNR %", r.adminSnrPct],
                ["hourlyRate", "Admin Hourly Rate ($)", r.hourlyRate],
              ].map(([key, label, val]) => (
                <div key={key}>
                  <label style={{ fontSize: 10, color: "var(--t3)", display: "block", marginBottom: 4 }}>{label}</label>
                  <input type="number" step={key === "hourlyRate" ? "0.50" : "0.5"} value={val} onChange={e => setRates(p => ({ ...p, [key]: Number(e.target.value) || 0 }))} style={{ width: "100%", background: "var(--bg-0)", border: "1px solid var(--border)", borderRadius: "var(--r-sm)", padding: "6px 10px", color: "var(--t1)", fontSize: 13, fontFamily: "'JetBrains Mono',monospace", fontWeight: 600, outline: 0 }} />
                </div>
              ))}
            </div>
          )}
        </div>
        {tab === "closers" && allClosers.map(c => renderPayCard(c, true))}
        {tab === "fronters" && allFronters.map(f => renderPayCard(f, true))}
        {tab === "admins" && allAdmins.map(a => renderPayCard(a, true))}
        {tab === "sent" && (
          <div className="fin">
            {Object.keys(sentSheets).length === 0 ? (
              <div className="empty" style={{ height: "auto", padding: 40 }}><div className="icon">ðŸ’µ</div><div className="txt">No paysheets sent yet</div></div>
            ) : (
              <div className="tbl-wrap"><table className="tbl"><thead><tr><th>Employee</th><th>Role</th><th>Final Pay</th><th>Sent By</th><th>Sent At</th><th>Actions</th></tr></thead>
                <tbody>{Object.entries(sentSheets).map(([userId, info]) => {
                  const u = users.find(x => x.id === userId);
                  return (
                    <tr key={userId}>
                      <td style={{ display: "flex", alignItems: "center", gap: 8 }}><div style={{ width: 26, height: 26, borderRadius: "50%", background: u?.color || "var(--bg-3)", display: "flex", alignItems: "center", justifyContent: "center", fontSize: 9, fontWeight: 600, color: "#fff" }}>{u?.avatar || "?"}</div><span style={{ fontWeight: 600 }}>{u?.name || "Unknown"}</span></td>
                      <td><span className="tag" style={{ background: ROLE_COLORS[u?.role] + "22", color: ROLE_COLORS[u?.role] }}>{ROLE_LABELS[u?.role] || u?.role}</span></td>
                      <td style={{ fontFamily: "'JetBrains Mono',monospace", fontWeight: 700, color: "var(--green)" }}>{fmt$(Math.max(0, info.amount))}</td>
                      <td style={{ color: "var(--t2)" }}>{info.sentBy}</td>
                      <td style={{ color: "var(--t3)", fontSize: 11 }}>{info.sentAt}</td>
                      <td><span className="tag" style={{ background: "var(--green-s)", color: "var(--green)" }}>DELIVERED</span></td>
                    </tr>
                  );
                })}</tbody>
              </table></div>
            )}
          </div>
        )}
        {tab === "history" && (
          <div className="fin">
            {!historyTab ? (
              <div style={{ textAlign: "center", padding: 40 }}><button className="btn btn-p" onClick={loadHistory}>Load Payroll History</button></div>
            ) : payrollHistory.length === 0 ? (
              <div className="empty" style={{ height: "auto", padding: 40 }}><div className="icon">ðŸ“‹</div><div className="txt">No payroll history found</div></div>
            ) : (
              <div className="tbl-wrap"><table className="tbl"><thead><tr><th>Employee</th><th>Role</th><th>Type</th><th>Week</th><th>Total Sold</th><th>Commission</th><th>Hourly Pay</th><th>Gross Pay</th><th>CB Total</th><th>Net Pay</th><th>Final Pay</th><th>Deals</th><th>Status</th></tr></thead>
                <tbody>{payrollHistory.map((h, i) => (
                  <tr key={i}>
                    <td style={{ fontWeight: 600 }}>{h.user_name}</td>
                    <td><span className="tag" style={{ background: "var(--bg-3)" }}>{h.user_role}</span></td>
                    <td style={{ textTransform: "capitalize" }}>{h.pay_type}</td>
                    <td style={{ fontSize: 11, color: "var(--t3)" }}>{h.week_start} â€” {h.week_end}</td>
                    <td style={{ fontFamily: "'JetBrains Mono',monospace" }}>{fmt$(h.total_sold)}</td>
                    <td style={{ fontFamily: "'JetBrains Mono',monospace", color: "var(--blue)" }}>{fmt$(h.commission_amount)}</td>
                    <td style={{ fontFamily: "'JetBrains Mono',monospace" }}>{fmt$(h.hourly_pay)}</td>
                    <td style={{ fontFamily: "'JetBrains Mono',monospace" }}>{fmt$(h.gross_pay)}</td>
                    <td style={{ fontFamily: "'JetBrains Mono',monospace", color: "var(--red)" }}>{fmt$(h.cb_total)}</td>
                    <td style={{ fontFamily: "'JetBrains Mono',monospace" }}>{fmt$(h.net_pay)}</td>
                    <td style={{ fontFamily: "'JetBrains Mono',monospace", fontWeight: 700, color: "var(--green)" }}>{fmt$(h.final_pay)}</td>
                    <td>{h.deal_count}</td>
                    <td><span className="tag" style={{ background: h.status === "sent" ? "var(--green-s)" : "var(--amber-s)", color: h.status === "sent" ? "var(--green)" : "var(--amber)" }}>{(h.status || "draft").toUpperCase()}</span></td>
                  </tr>
                ))}</tbody>
              </table></div>
            )}
          </div>
        )}
      </div>
    </div>
  );
}
// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
// TRANSFERS LOG â€” recycle icon, all transfers documented
// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
// SETTINGS â€” Master admin CRM customization
// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
function SettingsView({ crmName, setCrmName, dealStatuses, setDealStatuses, crmTheme, setCrmTheme, themes }) {
  const [newStatus, setNewStatus] = useState({ label: "", color: "#3b82f6" });
  const [editingIdx, setEditingIdx] = useState(null);

  const addStatus = () => {
    if (!newStatus.label.trim()) return;
    const id = newStatus.label.toLowerCase().replace(/\s+/g, "_");
    setDealStatuses(p => [...p, { id, label: newStatus.label, color: newStatus.color }]);
    setNewStatus({ label: "", color: "#3b82f6" });
  };

  const removeStatus = (idx) => {
    const s = dealStatuses[idx];
    if (["pending_admin", "charged", "chargeback", "cancelled"].includes(s.id)) { alert("Cannot remove core status: " + s.label); return; }
    setDealStatuses(p => p.filter((_, i) => i !== idx));
  };

  const updateStatus = (idx, field, val) => {
    setDealStatuses(p => p.map((s, i) => i === idx ? { ...s, [field]: val } : s));
  };

  const themeNames = { light: "Light (White)", dark: "Dark (Black)", blue: "Blue", green: "Green" };

  return (
    <div style={{ flex: 1, overflowY: "auto", padding: 20 }}>
      <div className="fin" style={{ marginBottom: 24 }}>
        <h2 style={{ fontSize: 20, fontWeight: 700, marginBottom: 4 }}>CRM Settings</h2>
        <p style={{ fontSize: 12, color: "var(--t3)" }}>Master Admin only â€” customize the CRM name, deal statuses, and appearance</p>
      </div>

      {/* CRM Name */}
      <div className="icard fin" style={{ marginBottom: 20 }}>
        <div style={{ fontSize: 14, fontWeight: 700, marginBottom: 12 }}>CRM Name</div>
        <div style={{ display: "flex", gap: 8, alignItems: "center" }}>
          <input value={crmName} onChange={e => setCrmName(e.target.value)} style={{ flex: 1, background: "var(--bg-2)", border: "1px solid var(--border)", borderRadius: "var(--r-sm)", padding: "10px 14px", color: "var(--t1)", fontSize: 16, fontWeight: 700, fontFamily: "inherit", outline: 0 }} />
          <div style={{ fontSize: 11, color: "var(--t3)" }}>This name appears on login screen and dashboard</div>
        </div>
      </div>

      {/* Theme / Template Selector */}
      <div className="icard fin" style={{ marginBottom: 20 }}>
        <div style={{ fontSize: 14, fontWeight: 700, marginBottom: 12 }}>CRM Template / Theme</div>
        <div style={{ display: "grid", gridTemplateColumns: "repeat(4, 1fr)", gap: 12 }}>
          {Object.entries(themes).map(([key, t]) => (
            <div key={key} onClick={() => setCrmTheme(key)} style={{ cursor: "pointer", border: crmTheme === key ? "2px solid var(--t1)" : "2px solid var(--border)", borderRadius: "var(--r)", padding: 12, textAlign: "center", transition: "var(--tr)" }}>
              <div style={{ display: "flex", gap: 4, justifyContent: "center", marginBottom: 8 }}>
                <div style={{ width: 20, height: 20, borderRadius: 4, background: t.bg0, border: "1px solid #ccc" }} />
                <div style={{ width: 20, height: 20, borderRadius: 4, background: t.bg2 }} />
                <div style={{ width: 20, height: 20, borderRadius: 4, background: t.t1 }} />
              </div>
              <div style={{ fontSize: 12, fontWeight: crmTheme === key ? 700 : 500, color: crmTheme === key ? "var(--t1)" : "var(--t2)" }}>{themeNames[key]}</div>
              {crmTheme === key && <div style={{ fontSize: 9, color: "var(--green)", marginTop: 4, fontWeight: 600 }}>ACTIVE</div>}
            </div>
          ))}
        </div>
      </div>

      {/* Deal Statuses */}
      <div className="icard fin" style={{ marginBottom: 20 }}>
        <div style={{ fontSize: 14, fontWeight: 700, marginBottom: 12 }}>Deal Statuses</div>
        <p style={{ fontSize: 11, color: "var(--t3)", marginBottom: 12 }}>Add, edit, or remove deal statuses. Core statuses (Pending Admin, Charged, Chargeback, Cancelled) cannot be removed.</p>
        <div style={{ marginBottom: 16 }}>
          {dealStatuses.map((s, idx) => (
            <div key={idx} style={{ display: "flex", alignItems: "center", gap: 10, padding: "8px 0", borderBottom: "1px solid var(--border)" }}>
              <input type="color" value={s.color.startsWith("var") ? "#3b82f6" : s.color} onChange={e => updateStatus(idx, "color", e.target.value)} style={{ width: 28, height: 28, border: "none", cursor: "pointer", borderRadius: 4 }} />
              {editingIdx === idx ? (
                <input value={s.label} onChange={e => updateStatus(idx, "label", e.target.value)} onBlur={() => setEditingIdx(null)} onKeyDown={e => e.key === "Enter" && setEditingIdx(null)} autoFocus style={{ flex: 1, background: "var(--bg-2)", border: "1px solid var(--border)", borderRadius: "var(--r-sm)", padding: "6px 10px", color: "var(--t1)", fontSize: 13, fontFamily: "inherit", outline: 0 }} />
              ) : (
                <div style={{ flex: 1, display: "flex", alignItems: "center", gap: 8 }}>
                  <span className="tag" style={{ background: (s.color.startsWith("var") ? s.color : s.color) + "22", color: s.color.startsWith("var") ? s.color : s.color }}>{s.label}</span>
                  <span style={{ fontSize: 10, color: "var(--t3)", fontFamily: "'JetBrains Mono',monospace" }}>{s.id}</span>
                </div>
              )}
              <button className="btn btn-sm" onClick={() => setEditingIdx(idx)}>Edit</button>
              <button className="btn btn-sm btn-d" onClick={() => removeStatus(idx)}>Remove</button>
            </div>
          ))}
        </div>
        <div style={{ display: "flex", gap: 8, alignItems: "center" }}>
          <input type="color" value={newStatus.color} onChange={e => setNewStatus(p => ({ ...p, color: e.target.value }))} style={{ width: 28, height: 28, border: "none", cursor: "pointer", borderRadius: 4 }} />
          <input value={newStatus.label} onChange={e => setNewStatus(p => ({ ...p, label: e.target.value }))} placeholder="New status name..." style={{ flex: 1, background: "var(--bg-2)", border: "1px solid var(--border)", borderRadius: "var(--r-sm)", padding: "8px 12px", color: "var(--t1)", fontSize: 12, fontFamily: "inherit", outline: 0 }} onKeyDown={e => e.key === "Enter" && addStatus()} />
          <button className="btn btn-sm btn-p" onClick={addStatus} disabled={!newStatus.label.trim()}>+ Add Status</button>
        </div>
      </div>

      {/* Quick reference */}
      <div className="icard fin">
        <div style={{ fontSize: 14, fontWeight: 700, marginBottom: 8 }}>Quick Reference</div>
        <div style={{ fontSize: 12, color: "var(--t2)", lineHeight: 1.8 }}>
          <div>CRM Name: changes login screen title and dashboard header</div>
          <div>Themes: instantly switches the entire CRM color scheme</div>
          <div>Deal Statuses: add custom workflow stages (e.g. "In Review", "Refund Pending")</div>
          <div>Color picker: click the colored square to change a status color</div>
          <div>All changes take effect immediately across the entire CRM</div>
        </div>
      </div>
    </div>
  );
}

// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
// WEEKLY DEAL TRACKER â€” Calendar view of deals per day per user
// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
function TrackerView({ deals, users, currentUser }) {
  const [weekOffset, setWeekOffset] = useState(0);

  // Calculate week Mon-Fri based on offset
  const now = new Date();
  const dayOfWeek = now.getDay();
  const thisMonday = new Date(now); thisMonday.setDate(now.getDate() - ((dayOfWeek + 6) % 7));
  const startDate = new Date(thisMonday); startDate.setDate(thisMonday.getDate() + weekOffset * 7);

  const days = [];
  for (let i = 0; i < 7; i++) {
    const d = new Date(startDate);
    d.setDate(startDate.getDate() + i);
    days.push(d);
  }

  const weekLabel = days[0].toLocaleDateString("en-US", { month: "short", day: "numeric" }) + " - " + days[6].toLocaleDateString("en-US", { month: "short", day: "numeric", year: "numeric" });

  const isSameDay = (dateStr, day) => {
    if (!dateStr) return false;
    const d = new Date(dateStr);
    return d.getFullYear() === day.getFullYear() && d.getMonth() === day.getMonth() && d.getDate() === day.getDate();
  };

  const chargedDeals = deals.filter(d => d.charged === "yes" && d.chargedBack !== "yes");
  const fronters = users.filter(u => u.role === "fronter");
  const closers = users.filter(u => u.role === "closer");

  // Get deals for a user on a specific day
  const userDayDeals = (userId, day, role) => {
    return chargedDeals.filter(d => {
      const dateField = d.chargedDate || d.timestamp;
      if (!isSameDay(dateField, day)) return false;
      if (role === "fronter") return d.fronter === userId;
      if (role === "closer") return d.closer === userId;
      return false;
    });
  };

  // Day totals
  const dayTotalDeals = (day) => chargedDeals.filter(d => isSameDay(d.chargedDate || d.timestamp, day));
  const dayTotalRev = (day) => dayTotalDeals(day).reduce((s, d) => s + (Number(d.fee) || 0), 0);

  // Week totals per user
  const userWeekDeals = (userId, role) => {
    return chargedDeals.filter(d => {
      const dt = new Date(d.chargedDate || d.timestamp);
      if (dt < days[0] || dt > new Date(days[6].getTime() + 86400000)) return false;
      if (role === "fronter") return d.fronter === userId;
      if (role === "closer") return d.closer === userId;
      return false;
    });
  };

  // Previous week totals per user
  const prevStart = new Date(days[0]); prevStart.setDate(prevStart.getDate() - 7);
  const prevEnd = new Date(days[0]);
  const userPrevWeekDeals = (userId, role) => {
    return chargedDeals.filter(d => {
      const dt = new Date(d.chargedDate || d.timestamp);
      if (dt < prevStart || dt >= prevEnd) return false;
      if (role === "fronter") return d.fronter === userId;
      if (role === "closer") return d.closer === userId;
      return false;
    });
  };
  const prevWeekLabel = prevStart.toLocaleDateString("en-US", { month: "short", day: "numeric" }) + " - " + new Date(prevEnd.getTime() - 86400000).toLocaleDateString("en-US", { month: "short", day: "numeric" });

  const dayNames = ["Mon", "Tue", "Wed", "Thu", "Fri", "Sat", "Sun"];

  const is = { background: "var(--bg-0)", border: "1px solid var(--border)", borderRadius: 3, padding: "3px 6px", fontSize: 11, fontFamily: "'JetBrains Mono',monospace", outline: 0, textAlign: "center" };

  return (
    <div style={{ flex: 1, display: "flex", flexDirection: "column", overflow: "hidden" }}>
      <div style={{ padding: "14px 20px", borderBottom: "1px solid var(--border)", display: "flex", justifyContent: "space-between", alignItems: "center", flexShrink: 0 }}>
        <div>
          <h2 style={{ fontSize: 16, fontWeight: 700 }}>Weekly Deal Tracker</h2>
          <p style={{ fontSize: 12, color: "var(--t3)", marginTop: 2 }}>{weekLabel}</p>
        </div>
        <div style={{ display: "flex", gap: 8, alignItems: "center" }}>
          <button className="btn btn-sm" onClick={() => setWeekOffset(p => p - 1)}>Prev</button>
          <button className="btn btn-sm btn-p" onClick={() => setWeekOffset(0)}>This Week</button>
          <button className="btn btn-sm" onClick={() => setWeekOffset(p => p + 1)}>Next</button>
        </div>
      </div>
      <div style={{ flex: 1, overflowY: "auto", padding: "16px 20px" }}>

        {/* Calendar grid */}
        <div style={{ overflowX: "auto" }}>
          <table className="tbl" style={{ minWidth: 900 }}>
            <thead>
              <tr>
                <th style={{ width: 140, position: "sticky", left: 0, background: "var(--bg-1)", zIndex: 2 }}>Agent</th>
                {days.map((d, i) => {
                  const isToday = isSameDay(now.toLocaleDateString(), d);
                  return <th key={i} style={{ textAlign: "center", minWidth: 110, background: isToday ? "#111" : "var(--bg-1)", color: isToday ? "#fff" : "var(--t1)" }}>{dayNames[i]}<br /><span style={{ fontSize: 10, fontWeight: 400 }}>{d.toLocaleDateString("en-US", { month: "numeric", day: "numeric" })}</span></th>;
                })}
                <th style={{ textAlign: "center", minWidth: 100, background: "var(--bg-2)" }}>Week Total</th>
                <th style={{ textAlign: "center", minWidth: 100, background: "var(--bg-3)" }}>Prev Week<br /><span style={{ fontSize: 9, fontWeight: 400, color: "var(--t3)" }}>{prevWeekLabel}</span></th>
              </tr>
            </thead>
            <tbody>
              {/* Fronters section */}
              <tr><td colSpan={10} style={{ background: "var(--pink-s)", fontWeight: 700, fontSize: 11, color: "var(--pink)", padding: "8px 12px" }}>FRONTERS</td></tr>
              {fronters.map(f => {
                const weekD = userWeekDeals(f.id, "fronter");
                const weekRev = weekD.reduce((s, d) => s + (Number(d.fee) || 0), 0);
                return (
                  <tr key={f.id}>
                    <td style={{ position: "sticky", left: 0, background: "var(--bg-0)", zIndex: 1 }}>
                      <div style={{ display: "flex", alignItems: "center", gap: 8 }}>
                        <div style={{ width: 24, height: 24, borderRadius: "50%", background: f.color, display: "flex", alignItems: "center", justifyContent: "center", fontSize: 9, fontWeight: 600, color: "#fff" }}>{f.avatar}</div>
                        <span style={{ fontWeight: 600, fontSize: 12 }}>{f.name}</span>
                      </div>
                    </td>
                    {days.map((d, i) => {
                      const dd = userDayDeals(f.id, d, "fronter");
                      const rev = dd.reduce((s, x) => s + (Number(x.fee) || 0), 0);
                      return (
                        <td key={i} style={{ textAlign: "center", verticalAlign: "top", padding: 6 }}>
                          {dd.length > 0 ? (
                            <div>
                              <div style={{ fontFamily: "'JetBrains Mono',monospace", fontWeight: 700, color: "var(--green)", fontSize: 13 }}>{fmt$(rev)}</div>
                              <div style={{ fontSize: 10, color: "var(--t2)" }}>{dd.length} deal{dd.length > 1 ? "s" : ""}</div>
                              <div style={{ marginTop: 4, display: "flex", flexWrap: "wrap", gap: 2, justifyContent: "center" }}>
                                {dd.map(x => <span key={x.id} title={x.ownerName + " " + fmt$(x.fee)} style={{ background: "var(--pink-s)", color: "var(--pink)", borderRadius: 3, padding: "1px 4px", fontSize: 9, fontWeight: 600 }}>{x.ownerName.split(" ").map(w => w[0]).join("")}</span>)}
                              </div>
                            </div>
                          ) : <span style={{ color: "var(--t3)", fontSize: 10 }}>-</span>}
                        </td>
                      );
                    })}
                    <td style={{ textAlign: "center", background: "var(--bg-2)", verticalAlign: "top", padding: 6 }}>
                      <div style={{ fontFamily: "'JetBrains Mono',monospace", fontWeight: 700, color: "var(--green)", fontSize: 14 }}>{fmt$(weekRev)}</div>
                      <div style={{ fontSize: 10, color: "var(--t2)" }}>{weekD.length} deal{weekD.length !== 1 ? "s" : ""}</div>
                    </td>
                    {(() => { const pw = userPrevWeekDeals(f.id, "fronter"); const pRev = pw.reduce((s, d) => s + (Number(d.fee) || 0), 0); const diff = weekRev - pRev; return (
                      <td style={{ textAlign: "center", background: "var(--bg-3)", verticalAlign: "top", padding: 6 }}>
                        <div style={{ fontFamily: "'JetBrains Mono',monospace", fontWeight: 700, color: pRev > 0 ? "var(--t1)" : "var(--t3)", fontSize: 13 }}>{fmt$(pRev)}</div>
                        <div style={{ fontSize: 10, color: "var(--t2)" }}>{pw.length} deal{pw.length !== 1 ? "s" : ""}</div>
                        {(weekRev > 0 || pRev > 0) && <div style={{ fontSize: 9, marginTop: 2, color: diff > 0 ? "var(--green)" : diff < 0 ? "var(--red)" : "var(--t3)" }}>{diff > 0 ? "+" : ""}{fmt$(diff)}</div>}
                      </td>
                    ); })()}
                  </tr>
                );
              })}

              {/* Closers section */}
              <tr><td colSpan={10} style={{ background: "var(--purple-s)", fontWeight: 700, fontSize: 11, color: "var(--purple)", padding: "8px 12px" }}>CLOSERS</td></tr>
              {closers.map(c => {
                const weekD = userWeekDeals(c.id, "closer");
                const weekRev = weekD.reduce((s, d) => s + (Number(d.fee) || 0), 0);
                return (
                  <tr key={c.id}>
                    <td style={{ position: "sticky", left: 0, background: "var(--bg-0)", zIndex: 1 }}>
                      <div style={{ display: "flex", alignItems: "center", gap: 8 }}>
                        <div style={{ width: 24, height: 24, borderRadius: "50%", background: c.color, display: "flex", alignItems: "center", justifyContent: "center", fontSize: 9, fontWeight: 600, color: "#fff" }}>{c.avatar}</div>
                        <span style={{ fontWeight: 600, fontSize: 12 }}>{c.name}</span>
                      </div>
                    </td>
                    {days.map((d, i) => {
                      const dd = userDayDeals(c.id, d, "closer");
                      const rev = dd.reduce((s, x) => s + (Number(x.fee) || 0), 0);
                      return (
                        <td key={i} style={{ textAlign: "center", verticalAlign: "top", padding: 6 }}>
                          {dd.length > 0 ? (
                            <div>
                              <div style={{ fontFamily: "'JetBrains Mono',monospace", fontWeight: 700, color: "var(--green)", fontSize: 13 }}>{fmt$(rev)}</div>
                              <div style={{ fontSize: 10, color: "var(--t2)" }}>{dd.length} deal{dd.length > 1 ? "s" : ""}</div>
                              <div style={{ marginTop: 4, display: "flex", flexWrap: "wrap", gap: 2, justifyContent: "center" }}>
                                {dd.map(x => <span key={x.id} title={x.ownerName + " " + fmt$(x.fee)} style={{ background: "var(--purple-s)", color: "var(--purple)", borderRadius: 3, padding: "1px 4px", fontSize: 9, fontWeight: 600 }}>{x.ownerName.split(" ").map(w => w[0]).join("")}</span>)}
                              </div>
                            </div>
                          ) : <span style={{ color: "var(--t3)", fontSize: 10 }}>-</span>}
                        </td>
                      );
                    })}
                    <td style={{ textAlign: "center", background: "var(--bg-2)", verticalAlign: "top", padding: 6 }}>
                      <div style={{ fontFamily: "'JetBrains Mono',monospace", fontWeight: 700, color: "var(--green)", fontSize: 14 }}>{fmt$(weekRev)}</div>
                      <div style={{ fontSize: 10, color: "var(--t2)" }}>{weekD.length} deal{weekD.length !== 1 ? "s" : ""}</div>
                    </td>
                    {(() => { const pw = userPrevWeekDeals(c.id, "closer"); const pRev = pw.reduce((s, d) => s + (Number(d.fee) || 0), 0); const diff = weekRev - pRev; return (
                      <td style={{ textAlign: "center", background: "var(--bg-3)", verticalAlign: "top", padding: 6 }}>
                        <div style={{ fontFamily: "'JetBrains Mono',monospace", fontWeight: 700, color: pRev > 0 ? "var(--t1)" : "var(--t3)", fontSize: 13 }}>{fmt$(pRev)}</div>
                        <div style={{ fontSize: 10, color: "var(--t2)" }}>{pw.length} deal{pw.length !== 1 ? "s" : ""}</div>
                        {(weekRev > 0 || pRev > 0) && <div style={{ fontSize: 9, marginTop: 2, color: diff > 0 ? "var(--green)" : diff < 0 ? "var(--red)" : "var(--t3)" }}>{diff > 0 ? "+" : ""}{fmt$(diff)}</div>}
                      </td>
                    ); })()}
                  </tr>
                );
              })}

              {/* Daily totals row */}
              <tr style={{ borderTop: "2px solid var(--t1)" }}>
                <td style={{ fontWeight: 700, position: "sticky", left: 0, background: "var(--bg-1)", zIndex: 1 }}>DAILY TOTAL</td>
                {days.map((d, i) => {
                  const dt = dayTotalDeals(d);
                  const dr = dayTotalRev(d);
                  return (
                    <td key={i} style={{ textAlign: "center", background: "var(--bg-1)", padding: 8 }}>
                      <div style={{ fontFamily: "'JetBrains Mono',monospace", fontWeight: 800, color: dr > 0 ? "var(--green)" : "var(--t3)", fontSize: 15 }}>{dr > 0 ? fmt$(dr) : "$0"}</div>
                      <div style={{ fontSize: 10, color: "var(--t2)", fontWeight: 600 }}>{dt.length} deal{dt.length !== 1 ? "s" : ""}</div>
                    </td>
                  );
                })}
                <td style={{ textAlign: "center", background: "#111", padding: 8 }}>
                  <div style={{ fontFamily: "'JetBrains Mono',monospace", fontWeight: 800, color: "#10b981", fontSize: 18 }}>{fmt$(days.reduce((s, d) => s + dayTotalRev(d), 0))}</div>
                  <div style={{ fontSize: 10, color: "#fff", fontWeight: 600 }}>{days.reduce((s, d) => s + dayTotalDeals(d).length, 0)} deals</div>
                </td>
                {(() => {
                  const prevDays = []; for (let i = 0; i < 7; i++) { const dd = new Date(prevStart); dd.setDate(prevStart.getDate() + i); prevDays.push(dd); }
                  const prevTotalRev = prevDays.reduce((s, d) => s + dayTotalRev(d), 0);
                  const prevTotalDeals = prevDays.reduce((s, d) => s + dayTotalDeals(d).length, 0);
                  const thisWeekRev = days.reduce((s, d) => s + dayTotalRev(d), 0);
                  const diff = thisWeekRev - prevTotalRev;
                  return (
                    <td style={{ textAlign: "center", background: "var(--bg-3)", padding: 8 }}>
                      <div style={{ fontFamily: "'JetBrains Mono',monospace", fontWeight: 800, color: prevTotalRev > 0 ? "var(--t1)" : "var(--t3)", fontSize: 16 }}>{fmt$(prevTotalRev)}</div>
                      <div style={{ fontSize: 10, color: "var(--t2)", fontWeight: 600 }}>{prevTotalDeals} deals</div>
                      {(thisWeekRev > 0 || prevTotalRev > 0) && <div style={{ fontSize: 10, marginTop: 2, fontWeight: 700, color: diff > 0 ? "var(--green)" : diff < 0 ? "var(--red)" : "var(--t3)" }}>{diff > 0 ? "+" : ""}{fmt$(diff)}</div>}
                    </td>
                  );
                })()}
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  );
}

// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
// TASKS â€” Assign tasks, require notes to complete
// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
function TasksView({ tasks, setTasks, users, currentUser, P, deals, leads }) {
  const [tab, setTab] = useState("my");
  const [selectedTask, setSelectedTask] = useState(null);
  const [newNote, setNewNote] = useState("");
  const [showCreate, setShowCreate] = useState(false);
  const [form, setForm] = useState({ title: "", type: "notes", assignedTo: "", dealId: "", leadId: "", clientName: "", priority: "medium", dueDate: "" });

  const isAdmin = P("view_all_leads");
  const myTasks = tasks.filter(t => t.assignedTo === currentUser.id);
  const allOpen = tasks.filter(t => t.status === "open");
  const allCompleted = tasks.filter(t => t.status === "completed");
  const filtered = tab === "my" ? myTasks : tab === "open" ? (isAdmin ? allOpen : myTasks.filter(t => t.status === "open")) : tab === "completed" ? (isAdmin ? allCompleted : myTasks.filter(t => t.status === "completed")) : isAdmin ? tasks : myTasks;
  const activeTask = tasks.find(t => t.id === selectedTask);

  const createTask = () => {
    if (!form.title || !form.assignedTo) return;
    setTasks(p => [...p, { ...form, id: uid(), createdBy: currentUser.id, status: "open", createdAt: todayStr(), notes: [{ text: "Task created: " + form.title, by: currentUser.id, time: nowT() }] }]);
    setForm({ title: "", type: "notes", assignedTo: "", dealId: "", leadId: "", clientName: "", priority: "medium", dueDate: "" });
    setShowCreate(false);
  };

  const addNote = () => {
    if (!newNote.trim() || !activeTask) return;
    setTasks(p => p.map(t => t.id === activeTask.id ? { ...t, notes: [...(t.notes || []), { text: newNote, by: currentUser.id, time: nowT() }] } : t));
    setNewNote("");
  };

  const completeTask = () => {
    if (!activeTask) return;
    if (!activeTask.notes || activeTask.notes.length < 2) { alert("You must add at least one note before completing this task."); return; }
    const lastNote = activeTask.notes[activeTask.notes.length - 1];
    if (lastNote.by !== currentUser.id) { alert("You must add your own note before marking as finished."); return; }
    setTasks(p => p.map(t => t.id === activeTask.id ? { ...t, status: "completed", completedAt: nowT() } : t));
  };

  const reopenTask = () => {
    if (!activeTask) return;
    setTasks(p => p.map(t => t.id === activeTask.id ? { ...t, status: "open", completedAt: null } : t));
  };

  const typeIcon = t => ({ notes: "ðŸ“", login: "ðŸ”‘", client: "ðŸ‘¤", deal: "ðŸ“‹", custom: "âš™" }[t] || "â˜‘");
  const prioColor = p => ({ high: "var(--red)", medium: "var(--amber)", low: "var(--green)" }[p] || "var(--t3)");

  return (
    <div style={{ flex: 1, display: "flex", overflow: "hidden" }}>
      {/* Task list panel */}
      <div style={{ flex: activeTask ? "0 0 45%" : 1, display: "flex", flexDirection: "column", overflow: "hidden", borderRight: activeTask ? "1px solid var(--border)" : "none" }}>
        <div style={{ padding: "14px 20px", borderBottom: "1px solid var(--border)", display: "flex", justifyContent: "space-between", alignItems: "center", flexShrink: 0 }}>
          <div>
            <h2 style={{ fontSize: 16, fontWeight: 700 }}>Tasks</h2>
            <p style={{ fontSize: 11, color: "var(--t3)", marginTop: 2 }}>{myTasks.filter(t => t.status === "open").length} open tasks assigned to you</p>
          </div>
          {isAdmin && <button className="btn btn-sm btn-p" onClick={() => setShowCreate(!showCreate)}>+ New Task</button>}
        </div>
        {/* Create task form */}
        {showCreate && isAdmin && (
          <div style={{ padding: 14, borderBottom: "1px solid var(--border)", background: "var(--bg-2)" }}>
            <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr", gap: 8, marginBottom: 8 }}>
              <div><label style={{ fontSize: 10, color: "var(--t3)", display: "block", marginBottom: 3 }}>Task Title</label><input value={form.title} onChange={e => setForm(p => ({ ...p, title: e.target.value }))} placeholder="What needs to be done..." style={{ width: "100%", background: "var(--bg-0)", border: "1px solid var(--border)", borderRadius: "var(--r-sm)", padding: "6px 10px", color: "var(--t1)", fontSize: 12, fontFamily: "inherit", outline: 0 }} /></div>
              <div><label style={{ fontSize: 10, color: "var(--t3)", display: "block", marginBottom: 3 }}>Assign To</label><select value={form.assignedTo} onChange={e => setForm(p => ({ ...p, assignedTo: e.target.value }))} style={{ width: "100%", background: "var(--bg-0)", border: "1px solid var(--border)", borderRadius: "var(--r-sm)", padding: "6px 10px", color: "var(--t1)", fontSize: 12, fontFamily: "inherit", outline: 0 }}><option value="">Select user...</option>{users.map(u => <option key={u.id} value={u.id}>{u.name} ({ROLE_LABELS[u.role]})</option>)}</select></div>
            </div>
            <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr 1fr 1fr", gap: 8, marginBottom: 8 }}>
              <div><label style={{ fontSize: 10, color: "var(--t3)", display: "block", marginBottom: 3 }}>Type</label><select value={form.type} onChange={e => setForm(p => ({ ...p, type: e.target.value }))} style={{ width: "100%", background: "var(--bg-0)", border: "1px solid var(--border)", borderRadius: "var(--r-sm)", padding: "6px 10px", color: "var(--t1)", fontSize: 11, fontFamily: "inherit", outline: 0 }}><option value="notes">Notes</option><option value="login">Login</option><option value="client">Client</option><option value="deal">Deal</option><option value="custom">Custom</option></select></div>
              <div><label style={{ fontSize: 10, color: "var(--t3)", display: "block", marginBottom: 3 }}>Priority</label><select value={form.priority} onChange={e => setForm(p => ({ ...p, priority: e.target.value }))} style={{ width: "100%", background: "var(--bg-0)", border: "1px solid var(--border)", borderRadius: "var(--r-sm)", padding: "6px 10px", color: "var(--t1)", fontSize: 11, fontFamily: "inherit", outline: 0 }}><option value="high">High</option><option value="medium">Medium</option><option value="low">Low</option></select></div>
              <div><label style={{ fontSize: 10, color: "var(--t3)", display: "block", marginBottom: 3 }}>Due Date</label><input type="date" value={form.dueDate} onChange={e => setForm(p => ({ ...p, dueDate: e.target.value }))} style={{ width: "100%", background: "var(--bg-0)", border: "1px solid var(--border)", borderRadius: "var(--r-sm)", padding: "6px 10px", color: "var(--t1)", fontSize: 11, fontFamily: "inherit", outline: 0 }} /></div>
              <div><label style={{ fontSize: 10, color: "var(--t3)", display: "block", marginBottom: 3 }}>Client Name</label><input value={form.clientName} onChange={e => setForm(p => ({ ...p, clientName: e.target.value }))} placeholder="Optional" style={{ width: "100%", background: "var(--bg-0)", border: "1px solid var(--border)", borderRadius: "var(--r-sm)", padding: "6px 10px", color: "var(--t1)", fontSize: 11, fontFamily: "inherit", outline: 0 }} /></div>
            </div>
            <div style={{ display: "flex", gap: 6 }}>
              <button className="btn btn-sm btn-p" onClick={createTask} disabled={!form.title || !form.assignedTo}>Create Task</button>
              <button className="btn btn-sm" onClick={() => setShowCreate(false)}>Cancel</button>
            </div>
          </div>
        )}
        <div className="tabs">
          {[["my", "My Tasks (" + myTasks.filter(t => t.status === "open").length + ")"], ["open", "All Open (" + allOpen.length + ")"], ["completed", "Completed (" + allCompleted.length + ")"], ["all", "All (" + tasks.length + ")"]].map(([k, l]) => (
            <div key={k} className={`tab ${tab === k ? "on" : ""}`} onClick={() => setTab(k)}>{l}</div>
          ))}
        </div>
        <div className="plist" style={{ flex: 1, overflow: "auto" }}>
          {filtered.map(t => {
            const assignedUser = users.find(u => u.id === t.assignedTo);
            const isOverdue = t.dueDate && t.status === "open" && new Date(t.dueDate) < new Date();
            return (
              <div key={t.id} className={`item ${selectedTask === t.id ? "on" : ""}`} onClick={() => setSelectedTask(t.id)} style={{ borderLeft: `3px solid ${prioColor(t.priority)}` }}>
                <div style={{ width: 28, height: 28, borderRadius: 6, background: t.status === "completed" ? "var(--green-s)" : "var(--bg-3)", display: "flex", alignItems: "center", justifyContent: "center", fontSize: 13, flexShrink: 0 }}>{t.status === "completed" ? "âœ“" : typeIcon(t.type)}</div>
                <div className="inf" style={{ flex: 1 }}>
                  <div className="nm" style={{ textDecoration: t.status === "completed" ? "line-through" : "none", color: t.status === "completed" ? "var(--t3)" : "var(--t1)" }}>{t.title}</div>
                  <div className="sub">{assignedUser?.name || "-"} {t.clientName ? " | " + t.clientName : ""} {isOverdue ? " | OVERDUE" : ""}</div>
                </div>
                <div style={{ textAlign: "right", flexShrink: 0 }}>
                  <span className="tag" style={{ background: prioColor(t.priority) + "22", color: prioColor(t.priority), fontSize: 8 }}>{t.priority}</span>
                  {t.dueDate && <div style={{ fontSize: 9, color: isOverdue ? "var(--red)" : "var(--t3)", marginTop: 2 }}>{t.dueDate}</div>}
                </div>
              </div>
            );
          })}
          {filtered.length === 0 && <div className="empty"><div className="icon">â˜‘</div><div className="txt">No tasks</div></div>}
        </div>
      </div>
      {/* Task detail panel */}
      {activeTask && (
        <div style={{ flex: "0 0 55%", display: "flex", flexDirection: "column", overflow: "hidden" }}>
          <div style={{ padding: "14px 20px", borderBottom: "1px solid var(--border)", display: "flex", justifyContent: "space-between", alignItems: "flex-start", flexShrink: 0 }}>
            <div>
              <div style={{ display: "flex", gap: 8, alignItems: "center" }}>
                <span style={{ fontSize: 18 }}>{typeIcon(activeTask.type)}</span>
                <h2 style={{ fontSize: 16, fontWeight: 700 }}>{activeTask.title}</h2>
              </div>
              <div style={{ fontSize: 11, color: "var(--t3)", marginTop: 4 }}>
                <span className="tag" style={{ background: activeTask.status === "completed" ? "var(--green-s)" : "var(--amber-s)", color: activeTask.status === "completed" ? "var(--green)" : "var(--amber)" }}>{activeTask.status === "completed" ? "COMPLETED" : "OPEN"}</span>
                <span className="tag" style={{ marginLeft: 6, background: prioColor(activeTask.priority) + "22", color: prioColor(activeTask.priority) }}>{activeTask.priority} priority</span>
              </div>
            </div>
            <div style={{ display: "flex", gap: 6 }}>
              {activeTask.status === "open" && (activeTask.assignedTo === currentUser.id || isAdmin) && <button className="btn btn-sm btn-g" onClick={completeTask}>Finished</button>}
              {activeTask.status === "completed" && isAdmin && <button className="btn btn-sm" onClick={reopenTask}>Reopen</button>}
              <button className="btn btn-sm" onClick={() => setSelectedTask(null)}>Close</button>
            </div>
          </div>
          <div style={{ flex: 1, overflowY: "auto", padding: 16 }}>
            <div className="igrid c3" style={{ marginBottom: 16 }}>
              <div className="icard"><div className="lbl">Assigned To</div><div className="val">{users.find(u => u.id === activeTask.assignedTo)?.name || "-"}</div></div>
              <div className="icard"><div className="lbl">Created By</div><div className="val">{users.find(u => u.id === activeTask.createdBy)?.name || "-"}</div></div>
              <div className="icard"><div className="lbl">Type</div><div className="val" style={{ textTransform: "capitalize" }}>{activeTask.type}</div></div>
              <div className="icard"><div className="lbl">Due Date</div><div className="val" style={{ color: activeTask.dueDate && activeTask.status === "open" && new Date(activeTask.dueDate) < new Date() ? "var(--red)" : "var(--t1)" }}>{activeTask.dueDate || "No due date"}</div></div>
              <div className="icard"><div className="lbl">Created</div><div className="val">{activeTask.createdAt}</div></div>
              {activeTask.completedAt && <div className="icard"><div className="lbl">Completed</div><div className="val" style={{ color: "var(--green)" }}>{activeTask.completedAt}</div></div>}
            </div>
            {activeTask.clientName && <div className="icard" style={{ marginBottom: 12 }}><div className="lbl">Client</div><div className="val" style={{ fontWeight: 600 }}>{activeTask.clientName}</div></div>}
            {activeTask.dealId && <div className="icard" style={{ marginBottom: 12 }}><div className="lbl">Linked Deal</div><div className="val">{deals.find(d => d.id === activeTask.dealId)?.ownerName || activeTask.dealId} - {fmt$(deals.find(d => d.id === activeTask.dealId)?.fee)}</div></div>}
            {activeTask.leadId && <div className="icard" style={{ marginBottom: 12 }}><div className="lbl">Linked Lead</div><div className="val">{leads.find(l => l.id === activeTask.leadId)?.ownerName || activeTask.leadId} - {leads.find(l => l.id === activeTask.leadId)?.resort}</div></div>}

            <div className="sec-title">Notes & Activity Log</div>
            <div style={{ marginBottom: 12 }}>
              {(activeTask.notes || []).map((n, i) => {
                const noteUser = users.find(u => u.id === n.by);
                return (
                  <div key={i} style={{ display: "flex", gap: 10, padding: "10px 0", borderBottom: "1px solid var(--border)" }}>
                    <div style={{ width: 28, height: 28, borderRadius: "50%", background: noteUser?.color || "var(--bg-3)", display: "flex", alignItems: "center", justifyContent: "center", fontSize: 9, fontWeight: 600, color: "#fff", flexShrink: 0 }}>{noteUser?.avatar || "?"}</div>
                    <div style={{ flex: 1 }}>
                      <div style={{ fontSize: 12, fontWeight: 600 }}>{noteUser?.name || "System"} <span style={{ fontWeight: 400, color: "var(--t3)", fontSize: 10 }}>{n.time}</span></div>
                      <div style={{ fontSize: 12, color: "var(--t2)", marginTop: 4, lineHeight: 1.5 }}>{n.text}</div>
                    </div>
                  </div>
                );
              })}
              {(!activeTask.notes || activeTask.notes.length === 0) && <div style={{ padding: 20, textAlign: "center", color: "var(--t3)", fontSize: 12 }}>No notes yet</div>}
            </div>

            {activeTask.status === "open" && (
              <div style={{ padding: 14, background: "var(--bg-2)", border: "1px solid var(--border)", borderRadius: "var(--r)" }}>
                <div style={{ fontSize: 11, fontWeight: 600, marginBottom: 8, color: "var(--t1)" }}>Add Note (required before completing)</div>
                <div style={{ display: "flex", gap: 6 }}>
                  <input value={newNote} onChange={e => setNewNote(e.target.value)} placeholder="Add your note..." style={{ flex: 1, background: "var(--bg-0)", border: "1px solid var(--border)", borderRadius: "var(--r-sm)", padding: "8px 12px", color: "var(--t1)", fontSize: 12, fontFamily: "inherit", outline: 0 }} onKeyDown={e => e.key === "Enter" && addNote()} />
                  <button className="btn btn-sm btn-p" onClick={addNote} disabled={!newNote.trim()}>Add Note</button>
                </div>
              </div>
            )}
            {activeTask.status === "completed" && (
              <div style={{ padding: 14, background: "var(--green-s)", border: "1px solid rgba(16,185,129,.3)", borderRadius: "var(--r)", fontSize: 12, color: "var(--green)", textAlign: "center" }}>Task completed on {activeTask.completedAt}</div>
            )}
          </div>
        </div>
      )}
    </div>
  );
}

function TransfersView({ transferLog, deals, leads, users, currentUser, setLeads, setDeals, onEditDeal }) {
  const [filter, setFilter] = useState("all");
  const [selectedTransfer, setSelectedTransfer] = useState(null);
  const [editingLead, setEditingLead] = useState(null);
  const isMasterOrAdmin = currentUser.role === "master_admin" || currentUser.role === "admin" || currentUser.role === "admin_limited";
  const myTransfers = currentUser.role === "master_admin" || isMasterOrAdmin ? transferLog : transferLog.filter(t => t.from === currentUser.id || t.to === currentUser.id);
  const filtered = filter === "all" ? myTransfers : myTransfers.filter(t => t.type === filter);
  const selectedDeal = selectedTransfer?.dealId ? deals.find(d => d.id === selectedTransfer.dealId) : null;
  const selectedLead = selectedTransfer?.leadId ? leads.find(l => l.id === selectedTransfer.leadId) : null;
  const showDetail = selectedDeal || selectedLead;

  const typeLabel = t => t.type === "fronter_to_closer" ? "Fronter -> Closer" : "Closer -> Admin";
  const typeColor = t => t.type === "fronter_to_closer" ? "var(--pink)" : "var(--blue)";

  // Inline lead editing
  const updateLead = (field, value) => {
    if (!selectedLead) return;
    setLeads(p => p.map(l => l.id === selectedLead.id ? { ...l, [field]: value } : l));
  };

  const LeadField = ({ label, field, mono }) => (
    <div className="icard">
      <div className="lbl">{label}</div>
      {isMasterOrAdmin && editingLead ? (
        <input value={selectedLead?.[field] || ""} onChange={e => updateLead(field, e.target.value)} style={{ background: "var(--bg-2)", border: "1px solid var(--border)", borderRadius: 3, padding: "4px 8px", color: "var(--t1)", fontSize: 12, fontFamily: mono ? "'JetBrains Mono',monospace" : "inherit", outline: 0, width: "100%" }} />
      ) : (
        <div className={`val ${mono ? "mono" : ""}`}>{selectedLead?.[field] || "-"}</div>
      )}
    </div>
  );

  return (
    <div style={{ flex: 1, display: "flex", overflow: "hidden" }}>
      {/* Transfer list */}
      <div style={{ flex: showDetail ? "0 0 50%" : 1, display: "flex", flexDirection: "column", overflow: "hidden", borderRight: showDetail ? "1px solid var(--border)" : "none" }}>
        <div style={{ padding: "16px 20px", borderBottom: "1px solid var(--border)", flexShrink: 0 }}>
          <h2 style={{ fontSize: 16, fontWeight: 700 }}>Transfer Log</h2>
          <p style={{ fontSize: 12, color: "var(--t3)", marginTop: 2 }}>{myTransfers.length} transfers | Click any row to view & edit</p>
        </div>
        <div className="tabs">
          {[["all", "All"], ["fronter_to_closer", "Fronter -> Closer"], ["closer_to_admin", "Closer -> Admin"]].map(([k, l]) => (
            <div key={k} className={`tab ${filter === k ? "on" : ""}`} onClick={() => setFilter(k)}>{l} ({(k === "all" ? myTransfers : myTransfers.filter(t => t.type === k)).length})</div>
          ))}
        </div>
        <div className="tbl-wrap" style={{ flex: 1, overflow: "auto", padding: "0 12px 12px" }}>
          <table className="tbl">
            <thead><tr><th>Type</th><th>From</th><th>To</th><th>Lead/Deal</th><th>Amount</th><th>Date</th></tr></thead>
            <tbody>
              {filtered.sort((a, b) => b.id.localeCompare(a.id)).map(t => {
                const fromU = users.find(u => u.id === t.from);
                const toU = users.find(u => u.id === t.to);
                const isSelected = selectedTransfer?.id === t.id;
                const clickable = !!t.dealId || !!t.leadId;
                return (
                  <tr key={t.id} onClick={() => { setSelectedTransfer(t); setEditingLead(false); }} style={{ cursor: "pointer", background: isSelected ? "var(--blue-s)" : "transparent" }}>
                    <td><span className="tag" style={{ background: typeColor(t) + "22", color: typeColor(t) }}>{typeLabel(t)}</span></td>
                    <td><span style={{ fontSize: 12 }}>{fromU?.name || "?"}</span></td>
                    <td><span style={{ fontSize: 12 }}>{toU?.name || "?"}</span></td>
                    <td style={{ fontWeight: 600 }}>
                      {t.leadName || t.dealName || "-"}
                      {t.dealId && <span style={{ marginLeft: 6, fontSize: 9, color: "var(--blue)" }}>View Deal</span>}
                      {t.leadId && !t.dealId && <span style={{ marginLeft: 6, fontSize: 9, color: "var(--pink)" }}>View Lead</span>}
                    </td>
                    <td style={{ fontFamily: "'JetBrains Mono',monospace", fontWeight: 600, color: t.amount ? "var(--green)" : "var(--t3)" }}>{t.amount ? fmt$(t.amount) : "-"}</td>
                    <td style={{ fontSize: 11, color: "var(--t3)" }}>{t.timestamp}</td>
                  </tr>
                );
              })}
              {filtered.length === 0 && <tr><td colSpan={6} style={{ textAlign: "center", color: "var(--t3)", padding: 40 }}>No transfers</td></tr>}
            </tbody>
          </table>
        </div>
      </div>
      {/* Detail panel â€” Lead or Deal */}
      {showDetail && (
        <div style={{ flex: "0 0 50%", display: "flex", flexDirection: "column", overflow: "hidden" }}>
          <div style={{ padding: "14px 20px", borderBottom: "1px solid var(--border)", display: "flex", justifyContent: "space-between", alignItems: "center", flexShrink: 0 }}>
            <div>
              <h2 style={{ fontSize: 16, fontWeight: 700 }}>{selectedDeal ? selectedDeal.ownerName : selectedLead?.ownerName}</h2>
              <div style={{ fontSize: 11, color: "var(--t3)", marginTop: 2 }}>{selectedDeal ? "Deal Sheet" : "Lead"} | {selectedDeal ? selectedDeal.resortName : selectedLead?.resort}</div>
            </div>
            <div style={{ display: "flex", gap: 6 }}>
              {isMasterOrAdmin && selectedDeal && <button className="btn btn-sm btn-p" onClick={() => onEditDeal(selectedDeal)}>âœŽ Edit Deal</button>}
              {isMasterOrAdmin && selectedLead && !editingLead && <button className="btn btn-sm btn-p" onClick={() => setEditingLead(true)}>âœŽ Edit Lead</button>}
              {isMasterOrAdmin && selectedLead && editingLead && <button className="btn btn-sm btn-g" onClick={() => setEditingLead(false)}>Save</button>}
              <button className="btn btn-sm" onClick={() => { setSelectedTransfer(null); setEditingLead(false); }}>Close</button>
            </div>
          </div>
          <div style={{ flex: 1, overflowY: "auto", padding: 16 }}>
            {/* â”€â”€ DEAL DETAIL â”€â”€ */}
            {selectedDeal && (<>
              <div className="igrid c3" style={{ marginBottom: 16 }}>
                <div className="icard"><div className="lbl">Fee</div><div className="val lg" style={{ color: "var(--green)" }}>{fmt$(selectedDeal.fee)}</div></div>
                <div className="icard"><div className="lbl">Status</div><div className="val"><span className="tag" style={{ background: selectedDeal.charged === "yes" ? "var(--green-s)" : "var(--amber-s)", color: selectedDeal.charged === "yes" ? "var(--green)" : "var(--amber)" }}>{(selectedDeal.status || "").replace("_"," ").toUpperCase()}</span></div></div>
                <div className="icard"><div className="lbl">Was VD</div><div className="val">{selectedDeal.wasVD || "No"}</div></div>
              </div>
              <div className="sec-title">Owner</div>
              <div className="igrid">
                <div className="icard"><div className="lbl">Name</div><div className="val">{selectedDeal.ownerName}</div></div>
                <div className="icard"><div className="lbl">Email</div><div className="val mono" style={{ fontSize: 11 }}>{selectedDeal.email || "-"}</div></div>
                <div className="icard"><div className="lbl">Phone</div><div className="val mono">{selectedDeal.primaryPhone}</div></div>
                <div className="icard"><div className="lbl">Address</div><div className="val">{selectedDeal.mailingAddress} {selectedDeal.cityStateZip}</div></div>
              </div>
              <div className="sec-title">Property</div>
              <div className="igrid c3">
                <div className="icard"><div className="lbl">Resort</div><div className="val">{selectedDeal.resortName}</div></div>
                <div className="icard"><div className="lbl">Location</div><div className="val">{selectedDeal.resortCityState}</div></div>
                <div className="icard"><div className="lbl">Bed/Bath</div><div className="val">{selectedDeal.bedBath || "-"}</div></div>
              </div>
              {isMasterOrAdmin && (<>
                <div className="sec-title">Payment / Card</div>
                <div className="igrid">
                  <div className="icard"><div className="lbl">Card</div><div className="val mono">{selectedDeal.cardType} {selectedDeal.cardNumber || "-"}</div></div>
                  <div className="icard"><div className="lbl">Exp/CV2</div><div className="val mono">{selectedDeal.expDate || "-"} / {selectedDeal.cv2 || "-"}</div></div>
                  <div className="icard"><div className="lbl">Bank</div><div className="val">{selectedDeal.bank || "-"}</div></div>
                  <div className="icard"><div className="lbl">Billing</div><div className="val">{selectedDeal.billingAddress || "-"}</div></div>
                </div>
              </>)}
              <div className="sec-title">Team</div>
              <div className="igrid c3">
                <div className="icard"><div className="lbl">Fronter</div><div className="val">{users.find(u => u.id === selectedDeal.fronter)?.name || "Self"}</div></div>
                <div className="icard"><div className="lbl">Closer</div><div className="val">{users.find(u => u.id === selectedDeal.closer)?.name || "-"}</div></div>
                <div className="icard"><div className="lbl">Admin</div><div className="val">{users.find(u => u.id === selectedDeal.assignedAdmin)?.name || "-"}</div></div>
              </div>
              {selectedDeal.correspondence?.length > 0 && (<><div className="sec-title">Correspondence</div>{selectedDeal.correspondence.map((c, i) => <div key={i} className="corr-item">{c}</div>)}</>)}
              {selectedDeal.notes && <div style={{ marginTop: 8, background: "var(--bg-2)", border: "1px solid var(--border)", borderRadius: "var(--r)", padding: 10, fontSize: 12, color: "var(--t2)" }}>{selectedDeal.notes}</div>}
            </>)}
            {/* â”€â”€ LEAD DETAIL â”€â”€ */}
            {selectedLead && !selectedDeal && (<>
              <div className="sec-title">Lead Info {editingLead && <span style={{ color: "var(--blue)", fontSize: 10, marginLeft: 8 }}>Editing - fields are live</span>}</div>
              <div className="igrid">
                <LeadField label="Owner Name" field="ownerName" />
                <LeadField label="Resort" field="resort" />
                <LeadField label="Phone 1" field="phone1" mono />
                <LeadField label="Phone 2" field="phone2" mono />
                <LeadField label="City" field="city" />
                <LeadField label="State" field="st" />
                <LeadField label="Zip" field="zip" />
                <LeadField label="Resort Location" field="resortLocation" />
              </div>
              <div className="sec-title" style={{ marginTop: 16 }}>Assignment</div>
              <div className="igrid c3">
                <div className="icard"><div className="lbl">Assigned To</div><div className="val">{users.find(u => u.id === selectedLead.assignedTo)?.name || "Unassigned"}</div></div>
                <div className="icard"><div className="lbl">Original Fronter</div><div className="val">{users.find(u => u.id === selectedLead.originalFronter)?.name || "-"}</div></div>
                <div className="icard"><div className="lbl">Transferred To</div><div className="val">{selectedLead.transferredTo === "verification" ? "Verification" : users.find(u => u.id === selectedLead.transferredTo)?.name || "-"}</div></div>
              </div>
              <div className="igrid">
                <div className="icard"><div className="lbl">Disposition</div><div className="val"><span className="tag" style={{ background: selectedLead.disposition?.includes("Transfer") ? "var(--pink-s)" : "var(--bg-3)", color: selectedLead.disposition?.includes("Transfer") ? "var(--pink)" : "var(--t1)" }}>{selectedLead.disposition || "Undisposed"}</span></div></div>
                <div className="icard"><div className="lbl">Source</div><div className="val" style={{ textTransform: "uppercase" }}>{selectedLead.source}</div></div>
              </div>
              {isMasterOrAdmin && editingLead && (
                <div style={{ marginTop: 16 }}>
                  <div className="sec-title">Reassign</div>
                  <select value={selectedLead.assignedTo || ""} onChange={e => updateLead("assignedTo", e.target.value)} style={{ background: "var(--bg-2)", border: "1px solid var(--border)", borderRadius: "var(--r-sm)", padding: "6px 10px", color: "var(--t1)", fontSize: 12, fontFamily: "inherit", outline: 0 }}>
                    <option value="">Unassigned</option>
                    {users.filter(u => u.role === "fronter" || u.role === "closer").map(u => <option key={u.id} value={u.id}>{u.name} ({ROLE_LABELS[u.role]})</option>)}
                  </select>
                </div>
              )}
            </>)}
          </div>
        </div>
      )}
    </div>
  );
}

function ChatViewFull({ chats, messages, users, currentUser, P, selectedChat, onSelectChat, onSend, onNewChat }) {
  const [input, setInput] = useState(""); const [showGif, setShowGif] = useState(false); const [gifCat, setGifCat] = useState("Trending"); const [gifSearch, setGifSearch] = useState(""); const endRef = useRef(null); const fileRef = useRef(null);
  const [showMentions, setShowMentions] = useState(false);
  const [mentionFilter, setMentionFilter] = useState("");
  const inputRef = useRef(null);
  const ac = chats.find(c => c.id === selectedChat);
  const cm = messages.filter(m => m.chatId === selectedChat);
  useEffect(() => { endRef.current?.scrollIntoView({ behavior: "smooth" }); }, [cm.length, selectedChat]);

  const handleSend = () => { onSend(selectedChat, input, "text"); setInput(""); setShowMentions(false); };
  const handleGif = gif => { onSend(selectedChat, gif.url, "gif", { title: gif.title }); setShowGif(false); };
  const handleFile = e => { const f = e.target.files[0]; if (!f) return; onSend(selectedChat, f.name, "file", { name: f.name, size: (f.size / 1024).toFixed(1) + " KB", type: f.type }); fileRef.current.value = ""; };

  const handleInputChange = (e) => {
    const val = e.target.value;
    setInput(val);
    const lastAt = val.lastIndexOf("@");
    if (lastAt >= 0 && (lastAt === 0 || val[lastAt - 1] === " ")) {
      const query = val.substring(lastAt + 1).toLowerCase();
      setMentionFilter(query);
      setShowMentions(true);
    } else {
      setShowMentions(false);
    }
  };

  const insertMention = (user) => {
    const lastAt = input.lastIndexOf("@");
    const before = input.substring(0, lastAt);
    setInput(before + "@" + user.name + " ");
    setShowMentions(false);
    inputRef.current?.focus();
  };

  const filteredUsers = users.filter(u => u.name.toLowerCase().includes(mentionFilter));

  // Render message text with @mentions highlighted
  const renderMsgText = (text) => {
    const parts = text.split(/(@\w[\w\s]*?)(?=\s|$|@)/g);
    return parts.map((part, i) => {
      const mentionMatch = part.match(/^@(.+)/);
      if (mentionMatch) {
        const mentionedName = mentionMatch[1].trim();
        const found = users.find(u => u.name.toLowerCase() === mentionedName.toLowerCase() || u.name.split(" ")[0].toLowerCase() === mentionedName.toLowerCase());
        if (found) {
          return <span key={i} style={{ color: "#2563eb", fontWeight: 800, fontSize: 15, textTransform: "uppercase", letterSpacing: ".5px" }}>{part}</span>;
        }
      }
      return <span key={i}>{part}</span>;
    });
  };

  // Better @mention parser using known user names
  const renderMsg = (text) => {
    if (!text) return text;
    let result = [];
    let remaining = text;
    let key = 0;
    const userNames = users.map(u => u.name).sort((a, b) => b.length - a.length);
    while (remaining.length > 0) {
      let found = false;
      for (const name of userNames) {
        const atName = "@" + name;
        const idx = remaining.indexOf(atName);
        if (idx >= 0) {
          if (idx > 0) result.push(<span key={key++}>{remaining.substring(0, idx)}</span>);
          result.push(<span key={key++} style={{ color: "#2563eb", fontWeight: 800, fontSize: 15, textTransform: "uppercase", letterSpacing: ".5px", background: "rgba(37,99,235,.1)", borderRadius: 3, padding: "1px 4px" }}>{"@" + name.toUpperCase()}</span>);
          remaining = remaining.substring(idx + atName.length);
          found = true;
          break;
        }
      }
      if (!found) { result.push(<span key={key++}>{remaining}</span>); break; }
    }
    return result;
  };

  return (<>
    <div className="panel" style={{width:160, minWidth:160}}>
      <div className="panel-hd"><div style={{display:"flex",justifyContent:"space-between",alignItems:"center"}}><h3>Messages</h3>{P("create_chats")&&<button className="btn btn-sm" onClick={onNewChat}>+</button>}</div></div>
      <div className="plist">
        {chats.filter(c=>c.type==="channel").map(ch=><div key={ch.id} className={`item ${selectedChat===ch.id?"on":""}`} onClick={()=>{onSelectChat(ch.id);setShowGif(false);setShowMentions(false)}}><span style={{width:20,textAlign:"center",fontSize:13}}>{ch.icon}</span><span className="nm" style={{flex:1}}>{ch.name}</span></div>)}
        {chats.filter(c=>c.type==="dm").length>0&&<><div style={{fontSize:10,color:"var(--t3)",textTransform:"uppercase",letterSpacing:".4px",padding:"8px 10px 4px",fontWeight:600}}>DMs</div>{chats.filter(c=>c.type==="dm").map(ch=><div key={ch.id} className={`item ${selectedChat===ch.id?"on":""}`} onClick={()=>{onSelectChat(ch.id);setShowGif(false);setShowMentions(false)}}><span style={{width:20,textAlign:"center",fontSize:13}}>ðŸ‘¤</span><span className="nm" style={{flex:1}}>{ch.name}</span></div>)}</>}
        {chats.filter(c=>c.type==="group").length>0&&<><div style={{fontSize:10,color:"var(--t3)",textTransform:"uppercase",letterSpacing:".4px",padding:"8px 10px 4px",fontWeight:600}}>Groups</div>{chats.filter(c=>c.type==="group").map(ch=><div key={ch.id} className={`item ${selectedChat===ch.id?"on":""}`} onClick={()=>{onSelectChat(ch.id);setShowGif(false);setShowMentions(false)}}><span style={{width:20,textAlign:"center",fontSize:13}}>ðŸ‘¤</span><span className="nm" style={{flex:1}}>{ch.name}</span></div>)}</>}
      </div>
    </div>
    <div className="chat-area" style={{position:"relative"}}>
      {ac?(<>
        <div className="chat-hd"><span style={{fontSize:16}}>{ac.icon||"ðŸ‘¤"}</span><div><h3>{ac.name}</h3><div style={{fontSize:11,color:"var(--t3)"}}>{ac.members.length} members</div></div></div>
        <div className="chat-msgs">{cm.map(m=>{const u=users.find(x=>x.id===m.userId); return (
          <div key={m.id} className="msg-row"><div className="msg-av" style={{background:u?.color||"var(--bg-3)"}}>{u?.avatar||"?"}</div><div className="msg-body"><div className="msg-nm">{u?.name||"?"} <span>{m.time}</span></div>
          {(!m.type||m.type==="text")&&<div className="msg-txt">{renderMsg(m.text)}</div>}
          {m.type==="gif"&&<img src={m.text} alt="GIF" className="msg-gif"/>}
          {m.type==="file"&&<div className="msg-file">ðŸ“Ž {m.meta?.name} <span style={{color:"var(--t3)",fontSize:10}}>({m.meta?.size})</span></div>}
        </div></div>);})}<div ref={endRef}/></div>
        {showGif&&<div className="gif-picker">
          <div className="gif-cats">{Object.keys(GIF_LIBRARY).map(cat=> <div key={cat} className={`gif-cat ${gifCat===cat?"on":""}`} onClick={()=>{setGifCat(cat);setGifSearch("")}}>{cat}</div>)}</div>
          <div className="gif-search"><input placeholder="Search GIFs..." value={gifSearch} onChange={e=>setGifSearch(e.target.value)} /></div>
          <div className="gif-grid">{(gifSearch ? Object.values(GIF_LIBRARY).flat().filter(g=>g.title.toLowerCase().includes(gifSearch.toLowerCase())) : GIF_LIBRARY[gifCat]||[]).map(g=> <img key={g.id} src={g.url} alt={g.title} title={g.title} onClick={()=>handleGif(g)} />)}{(gifSearch && Object.values(GIF_LIBRARY).flat().filter(g=>g.title.toLowerCase().includes(gifSearch.toLowerCase())).length===0) && <div style={{gridColumn:"span 3",textAlign:"center",padding:20,color:"var(--t3)",fontSize:12}}>No GIFs found for "{gifSearch}"</div>}</div>
        </div>}
        {/* @Mention popup */}
        {showMentions && filteredUsers.length > 0 && (
          <div style={{ position: "absolute", bottom: 60, left: 12, right: 12, background: "var(--bg-0)", border: "1px solid var(--border)", borderRadius: "var(--r)", boxShadow: "0 4px 20px rgba(0,0,0,.15)", maxHeight: 180, overflowY: "auto", zIndex: 10 }}>
            <div style={{ padding: "6px 10px", fontSize: 10, color: "var(--t3)", fontWeight: 600, textTransform: "uppercase", borderBottom: "1px solid var(--border)" }}>Mention a user</div>
            {filteredUsers.map(u => (
              <div key={u.id} onClick={() => insertMention(u)} style={{ display: "flex", alignItems: "center", gap: 10, padding: "8px 12px", cursor: "pointer", borderBottom: "1px solid var(--border)", transition: "background .15s" }} onMouseEnter={e => e.currentTarget.style.background = "var(--bg-2)"} onMouseLeave={e => e.currentTarget.style.background = "transparent"}>
                <div style={{ width: 24, height: 24, borderRadius: "50%", background: u.color, display: "flex", alignItems: "center", justifyContent: "center", fontSize: 9, fontWeight: 600, color: "#fff" }}>{u.avatar}</div>
                <div>
                  <div style={{ fontSize: 12, fontWeight: 600, color: "var(--t1)" }}>{u.name}</div>
                  <div style={{ fontSize: 10, color: "var(--t3)" }}>{ROLE_LABELS[u.role]}</div>
                </div>
              </div>
            ))}
          </div>
        )}
        <div className="chat-inp">
          <div className="chat-inp-box">
            <button className="chat-tool-btn" onClick={()=>setShowGif(!showGif)} title="GIFs">GIF</button>
            <button className="chat-tool-btn" onClick={()=>fileRef.current?.click()} title="Attach PDF">ðŸ“Ž</button>
            <input type="file" ref={fileRef} onChange={handleFile} accept=".pdf,.doc,.docx,.png,.jpg" style={{display:"none"}} />
            <input ref={inputRef} placeholder={`Message ${ac.name}... (type @ to mention)`} value={input} onChange={handleInputChange} onKeyDown={e=>{if(e.key==="Enter")handleSend();if(e.key==="Escape")setShowMentions(false)}} />
            <button className="send-btn" onClick={handleSend}>â†‘</button>
          </div>
        </div>
      </>):<div className="empty"><div className="icon">ðŸ’¬</div><div className="txt">Select a chat</div></div>}
    </div>
  </>);
}

// â•â•â• USERS with permission editing â•â•â•
function UsersView({ users, setUsers, currentUser, P, onAdd, onEditPerms }) {
  const [editingCreds, setEditingCreds] = useState(null);
  const [credForm, setCredForm] = useState({ username: "", password: "" });

  const startEditCreds = (u) => { setEditingCreds(u.id); setCredForm({ username: u.username || "", password: u.password || "" }); };
  const saveCreds = () => { setUsers(p => p.map(u => u.id === editingCreds ? { ...u, username: credForm.username, password: credForm.password } : u)); setEditingCreds(null); };

  return <div style={{ flex: 1, display: "flex", flexDirection: "column", overflow: "hidden" }}>
    <div style={{ padding: "16px 20px", borderBottom: "1px solid var(--border)", display: "flex", justifyContent: "space-between", alignItems: "center", flexShrink: 0 }}>
      <h2 style={{ fontSize: 16, fontWeight: 700 }}>Users ({users.length})</h2>
      {P("edit_users") && <button className="btn btn-p btn-sm" onClick={onAdd}>+ Add User</button>}
    </div>
    <div className="tbl-wrap" style={{ flex: 1, overflow: "auto", padding: "0 20px" }}>
      <table className="tbl"><thead><tr><th>Name</th><th>Username</th><th>Password</th><th>Email</th><th>Role</th><th>Perms</th><th>Actions</th></tr></thead>
        <tbody>{users.map(u => (
          <tr key={u.id}>
            <td style={{ display: "flex", alignItems: "center", gap: 8 }}><div style={{ width: 28, height: 28, borderRadius: "50%", background: u.color, display: "flex", alignItems: "center", justifyContent: "center", fontSize: 10, fontWeight: 600, color: "#fff" }}>{u.avatar}</div><span style={{ fontWeight: 500 }}>{u.name}</span></td>
            <td style={{ fontFamily: "'JetBrains Mono',monospace", fontSize: 11 }}>
              {editingCreds === u.id ? <input value={credForm.username} onChange={e => setCredForm(p => ({ ...p, username: e.target.value }))} style={{ background: "var(--bg-2)", border: "1px solid var(--border)", borderRadius: 3, padding: "3px 6px", color: "var(--t1)", fontSize: 11, fontFamily: "'JetBrains Mono',monospace", outline: 0, width: 90 }} /> : (u.username || "-")}
            </td>
            <td style={{ fontFamily: "'JetBrains Mono',monospace", fontSize: 11 }}>
              {editingCreds === u.id ? <input value={credForm.password} onChange={e => setCredForm(p => ({ ...p, password: e.target.value }))} style={{ background: "var(--bg-2)", border: "1px solid var(--border)", borderRadius: 3, padding: "3px 6px", color: "var(--t1)", fontSize: 11, fontFamily: "'JetBrains Mono',monospace", outline: 0, width: 90 }} /> : (P("edit_users") ? (u.password || "-") : "********")}
            </td>
            <td style={{ fontFamily: "'JetBrains Mono',monospace", fontSize: 11 }}>{u.email}</td>
            <td>{P("edit_users") ? <select value={u.role} onChange={e => { const r = e.target.value; setUsers(p => p.map(x => x.id === u.id ? { ...x, role: r, permissions: ROLE_DEFAULTS[r] || [] } : x)); }} style={{ background: "var(--bg-2)", border: "1px solid var(--border)", borderRadius: "var(--r-sm)", padding: "4px 8px", color: "var(--t1)", fontSize: 11, fontFamily: "inherit" }}><option value="master_admin">Master Admin</option><option value="admin">Admin</option><option value="admin_limited">Admin (Limited)</option><option value="fronter">Fronter</option><option value="closer">Closer</option></select> : <span className="tag" style={{ background: ROLE_COLORS[u.role] + "22", color: ROLE_COLORS[u.role] }}>{ROLE_LABELS[u.role]}</span>}</td>
            <td><span style={{ fontSize: 10, color: "var(--t3)" }}>{u.permissions?.length || 0}</span>{P("edit_users") && <button className="btn btn-sm" style={{ marginLeft: 4 }} onClick={() => onEditPerms(u)}>âœŽ</button>}</td>
            <td style={{ whiteSpace: "nowrap" }}>
              {P("edit_users") && editingCreds === u.id && (<>
                <button className="btn btn-sm btn-g" onClick={saveCreds} disabled={credForm.password.length < 8}>Save</button>
                <button className="btn btn-sm" style={{ marginLeft: 4 }} onClick={() => setEditingCreds(null)}>Cancel</button>
              </>)}
              {P("edit_users") && editingCreds !== u.id && <button className="btn btn-sm" onClick={() => startEditCreds(u)}>Edit Credentials</button>}
              {P("delete_users") && u.id !== currentUser.id && (u.role === "fronter" || u.role === "closer" || currentUser.role === "master_admin") && <button className="btn btn-sm btn-d" style={{ marginLeft: 4 }} onClick={() => setUsers(p => p.filter(x => x.id !== u.id))}>Remove</button>}
            </td>
          </tr>
        ))}</tbody>
      </table>
    </div>
  </div>;
}

// â•â•â• MODALS â•â•â•
function CSVImportModal({onClose,onImport}){const[text,setText]=useState("");const fr=useRef(null);const hf=e=>{const f=e.target.files[0];if(!f)return;const r=new FileReader();r.onload=ev=>setText(ev.target.result);r.readAsText(f)};return <div className="modal-overlay" onClick={onClose}><div className="modal fin" onClick={e=>e.stopPropagation()}><h3>ðŸ“¥ Import CSV</h3><p style={{fontSize:12,color:"var(--t3)",marginBottom:12}}>Resort, Owner Name, Phone1, Phone2, City, State, Zip, Location</p><input type="file" accept=".csv" ref={fr} onChange={hf} style={{marginBottom:12,fontSize:12}}/><div className="fg"><label>Or paste CSV:</label><textarea rows={6} value={text} onChange={e=>setText(e.target.value)} /></div><div className="modal-actions"><button className="btn" onClick={onClose}>Cancel</button><button className="btn btn-p" onClick={()=>onImport(text)} disabled={!text.trim()}>Import</button></div></div></div>}

function AddLeadModal({onClose,onSave}){const[f,setF]=useState({resort:"",ownerName:"",phone1:"",phone2:"",city:"",st:"",zip:"",resortLocation:""});return <div className="modal-overlay" onClick={onClose}><div className="modal fin" onClick={e=>e.stopPropagation()}><h3>+ Add Lead</h3><div className="form-grid">{[["Resort","resort"],["Owner","ownerName"],["Phone 1","phone1"],["Phone 2","phone2"],["City","city"],["State","st"],["Zip","zip"],["Location","resortLocation"]].map(([l,k])=><div key={k} className="fg"><label>{l}</label><input value={f[k]} onChange={e=>setF(p=>({...p,[k]:e.target.value}))}/></div>)}</div><div className="modal-actions"><button className="btn" onClick={onClose}>Cancel</button><button className="btn btn-p" onClick={()=>onSave(f)}>Add</button></div></div></div>}

function AssignModal({users,onClose,onAssign}){return <div className="modal-overlay" onClick={onClose}><div className="modal fin" onClick={e=>e.stopPropagation()}><h3>Assign To</h3>{users.filter(u=>u.role==="fronter"||u.role==="closer").map(u=><div key={u.id} className="item" onClick={()=>onAssign(u.id)} style={{marginBottom:4}}><div className="av" style={{background:u.color}}>{u.avatar}</div><div className="inf"><div className="nm">{u.name}</div><div className="sub">{ROLE_LABELS[u.role]}</div></div></div>)}<div className="modal-actions"><button className="btn" onClick={onClose}>Cancel</button></div></div></div>}

function DealFormModal({deal,users,currentUser,onClose,onSave}){
  const blank={id:uid(),timestamp:todayStr(),chargedDate:"",wasVD:"",fronter:"",closer:currentUser.role==="closer"?currentUser.id:"",fee:"",ownerName:"",mailingAddress:"",cityStateZip:"",primaryPhone:"",secondaryPhone:"",email:"",weeks:"",askingRental:"",resortName:"",resortCityState:"",exchangeGroup:"",bedBath:"",usage:"",askingSalePrice:"",nameOnCard:"",cardType:"",bank:"",cardNumber:"",expDate:"",cv2:"",billingAddress:"",bank2:"",cardNumber2:"",expDate2:"",cv2_2:"",usingTimeshare:"",lookingToGetOut:"",verificationNum:"",notes:"",loginInfo:"",correspondence:[],files:[],snr:"",login:"",merchant:"",appLogin:"",assignedAdmin:"",status:"pending_admin",charged:"no",chargedBack:"no"};
  const[f,setF]=useState(deal||blank);const upd=(k,v)=>setF(p=>({...p,[k]:v}));
  const[newCorr,setNewCorr]=useState("");
  const addCorr=()=>{if(!newCorr.trim())return;setF(p=>({...p,correspondence:[...p.correspondence,newCorr]}));setNewCorr("")};
  const fronters=users.filter(u=>u.role==="fronter");const closers=users.filter(u=>u.role==="closer");
  const adminUsers=users.filter(u=>u.role==="admin"||u.role==="admin_limited"||u.role==="master_admin");
  const F=({label,k,span2,type="text",options})=> <div className={`fg ${span2?"span2":""}`}><label>{label}</label>{options?<select value={f[k]} onChange={e=>upd(k,e.target.value)}><option value="">...</option>{options.map(o=><option key={o.value||o} value={o.value||o}>{o.label||o}</option>)}</select>:type==="textarea"?<textarea value={f[k]} onChange={e=>upd(k,e.target.value)}/>:<input type={type} value={f[k]} onChange={e=>upd(k,e.target.value)}/> }</div>;
  return <div className="modal-overlay" onClick={onClose}><div className="modal fin" onClick={e=>e.stopPropagation()} style={{maxWidth:680}}>
    <h3>{deal?"Edit Deal":"New Deal Sheet"}</h3>
    <div style={{padding:"10px 14px",background:"var(--amber-s)",border:"1px solid var(--amber)",borderRadius:"var(--r)",marginBottom:16,fontSize:12,color:"var(--amber)"}}>
      {deal ? "Editing existing deal" : "After saving, this deal will be sent to the assigned admin for verification and charging."}
    </div>
    <div className="sec-title">Deal Info & Admin Assignment</div>
    <div className="form-grid c3"><F label="Was VD?" k="wasVD" options={["Yes","No"]}/><F label="Fronter" k="fronter" options={fronters.map(u=>({value:u.id,label:u.name}))}/><F label="Closer" k="closer" options={closers.map(u=>({value:u.id,label:u.name}))}/><F label="Fee" k="fee"/></div>
    <div className="form-grid" style={{marginTop:8}}>
      <div className="fg span2"><label style={{color:"var(--amber)",fontWeight:700}}>Assign to Admin (for verification & charging)</label><select value={f.assignedAdmin} onChange={e=>upd("assignedAdmin",e.target.value)} style={{borderColor:"var(--amber)"}}><option value="">Select admin agent...</option>{adminUsers.map(u=><option key={u.id} value={u.id}>{u.name} ({ROLE_LABELS[u.role]})</option>)}</select></div>
    </div>
    <div className="sec-title">Owner</div>
    <div className="form-grid"><F label="Name" k="ownerName"/><F label="Email" k="email"/><F label="Address" k="mailingAddress"/><F label="City/St/Zip" k="cityStateZip"/><F label="Phone 1" k="primaryPhone"/><F label="Phone 2" k="secondaryPhone"/></div>
    <div className="sec-title">Property</div>
    <div className="form-grid c3"><F label="Weeks" k="weeks"/><F label="Ask Rental" k="askingRental"/><F label="Ask Sale" k="askingSalePrice"/><F label="Resort" k="resortName" span2/><F label="Resort City/St" k="resortCityState"/><F label="Exchange" k="exchangeGroup"/><F label="Bed/Bath" k="bedBath"/><F label="Usage" k="usage" options={["Annual","Biennial","Points","Other"]}/></div>
    <div className="sec-title">Payment / Card Info</div>
    <div className="form-grid"><F label="Name on Card" k="nameOnCard"/><F label="Card Type" k="cardType" options={["Visa","Mastercard","Amex","Discover"]}/><F label="Bank" k="bank"/><F label="Full Card # & Charge Amt" k="cardNumber"/><F label="Exp (MM/YY)" k="expDate"/><F label="CV2" k="cv2"/><F label="Billing Addr" k="billingAddress" span2/></div>
    <div className="sec-title">2nd Card (Optional)</div>
    <div className="form-grid"><F label="2nd Bank" k="bank2"/><F label="2nd Card # & Amt" k="cardNumber2"/><F label="2nd Exp" k="expDate2"/><F label="2nd CV2" k="cv2_2"/></div>
    <div className="sec-title">Login & Merchant Info</div>
    <div className="form-grid"><F label="Login URL" k="login"/><F label="Merchant" k="merchant"/><F label="App Login" k="appLogin"/><F label="V#" k="verificationNum"/></div>
    <div className="form-grid c1" style={{marginTop:8}}><F label="Login Info (credentials)" k="loginInfo" type="textarea"/></div>
    <div className="sec-title">Correspondence Log</div>
    {f.correspondence?.map((c,i)=> <div key={i} className="corr-item">{c}</div>)}
    <div style={{display:"flex",gap:6,marginTop:6}}><input value={newCorr} onChange={e=>setNewCorr(e.target.value)} placeholder="Add note..." style={{flex:1,background:"var(--bg-2)",border:"1px solid var(--border)",borderRadius:"var(--r-sm)",padding:"7px 10px",color:"var(--t1)",fontSize:12,fontFamily:"inherit",outline:0}} onKeyDown={e=>e.key==="Enter"&&addCorr()}/><button className="btn btn-sm btn-p" onClick={addCorr}>Add</button></div>
    <div className="form-grid c1" style={{marginTop:12}}><F label="Notes" k="notes" type="textarea"/></div>
    <div className="modal-actions"><button className="btn" onClick={onClose}>Cancel</button><button className="btn btn-p" onClick={()=>onSave(f)}>ðŸ’¾ {deal ? "Save Changes" : "Submit to Admin"}</button></div>
  </div></div>;
}

function NewChatModal({users,currentUser,onClose,onCreate}){const[type,setType]=useState("group");const[name,setName]=useState("");const[sel,setSel]=useState([]);const others=users.filter(u=>u.id!==currentUser.id);const toggle=id=>{if(type==="dm")setSel([id]);else setSel(p=>p.includes(id)?p.filter(x=>x!==id):[...p,id])};
  return <div className="modal-overlay" onClick={onClose}><div className="modal fin" onClick={e=>e.stopPropagation()}>
    <h3>New Conversation</h3>
    <div style={{display:"flex",gap:6,marginBottom:14}}>{[["dm","DM"],["group","Group"],["channel","Channel"]].map(([k,l])=><button key={k} className={`btn btn-sm ${type===k?"btn-p":""}`} onClick={()=>{setType(k);setSel([])}}>{l}</button>)}</div>
    {type!=="dm"&&<div className="fg" style={{marginBottom:12}}><label>Name</label><input value={name} onChange={e=>setName(e.target.value)}/></div>}
    <div style={{fontSize:10,color:"var(--t3)",textTransform:"uppercase",letterSpacing:".4px",marginBottom:6,fontWeight:600}}>{type==="dm"?"Select user":"Select members"}</div>
    {others.map(u=><div key={u.id} className={`item ${sel.includes(u.id)?"on":""}`} onClick={()=>toggle(u.id)} style={{marginBottom:2}}><div className="av" style={{background:u.color,width:26,height:26,fontSize:9}}>{u.avatar}</div><div className="inf"><div className="nm">{u.name}</div><div className="sub">{ROLE_LABELS[u.role]}</div></div>{sel.includes(u.id)&&<span style={{color:"var(--blue)"}}>âœ“</span>}</div>)}
    <div className="modal-actions"><button className="btn" onClick={onClose}>Cancel</button><button className="btn btn-p" onClick={()=>{const m=[currentUser.id,...sel];if(type==="dm"){const o=users.find(u=>u.id===sel[0]);onCreate({type:"dm",name:o?.name||"DM",icon:"ðŸ‘¤",members:m})}else onCreate({type,name:name||"New "+type,icon:type==="channel"?"#":"ðŸ‘¤",members:m})}} disabled={sel.length===0}>Create</button></div>
  </div></div>;
}

function AddUserModal({onClose,onSave}){const[f,setF]=useState({name:"",email:"",role:"fronter",username:"",password:""});const colors=["#3b82f6","#10b981","#ec4899","#f59e0b","#8b5cf6","#14b8a6","#ef4444","#6366f1"];
  return <div className="modal-overlay" onClick={onClose}><div className="modal fin" onClick={e=>e.stopPropagation()}>
    <h3>+ Add User</h3>
    <div className="form-grid">
      <div className="fg"><label>Full Name</label><input value={f.name} onChange={e=>setF(p=>({...p,name:e.target.value}))}/></div>
      <div className="fg"><label>Email</label><input value={f.email} onChange={e=>setF(p=>({...p,email:e.target.value}))}/></div>
      <div className="fg"><label>Username</label><input value={f.username} onChange={e=>setF(p=>({...p,username:e.target.value}))} placeholder="e.g. jsmith"/></div>
      <div className="fg"><label>Password (8 digit min)</label><input value={f.password} onChange={e=>setF(p=>({...p,password:e.target.value}))} placeholder="8 characters min"/></div>
      <div className="fg span2"><label>Role</label><select value={f.role} onChange={e=>setF(p=>({...p,role:e.target.value}))}><option value="master_admin">Master Admin</option><option value="admin">Admin</option><option value="admin_limited">Admin (Limited)</option><option value="fronter">Fronter</option><option value="closer">Closer</option></select></div>
    </div>
    {f.password && f.password.length < 8 && <div style={{color:"var(--red)",fontSize:11,marginTop:6}}>Password must be at least 8 characters</div>}
    <div className="modal-actions"><button className="btn" onClick={onClose}>Cancel</button><button className="btn btn-p" disabled={!f.name||!f.username||f.password.length<8} onClick={()=>{const av=f.name.split(" ").map(w=>w[0]).join("").slice(0,2).toUpperCase();onSave({...f,avatar:av,color:colors[Math.floor(Math.random()*colors.length)],permissions:ROLE_DEFAULTS[f.role]||[]})}}>Add User</button></div>
  </div></div>;
}

// â•â•â• EDIT PERMISSIONS MODAL â•â•â•
function EditPermsModal({user,users,setUsers,onClose}){
  const[perms,setPerms]=useState([...(user.permissions||[])]);
  const toggle=k=>setPerms(p=>p.includes(k)?p.filter(x=>x!==k):[...p,k]);
  const groups={};ALL_PERMISSIONS.forEach(p=>{if(!groups[p.group])groups[p.group]=[];groups[p.group].push(p)});
  const save=()=>{setUsers(p=>p.map(u=>u.id===user.id?{...u,permissions:perms}:u));onClose()};
  return <div className="modal-overlay" onClick={onClose}><div className="modal fin" onClick={e=>e.stopPropagation()} style={{maxWidth:600}}>
    <h3>Permissions â€” {user.name}</h3>
    <p style={{fontSize:12,color:"var(--t3)",marginBottom:16}}>Role: {ROLE_LABELS[user.role]} Â· Toggle individual permissions below</p>
    <div style={{display:"flex",gap:6,marginBottom:16,flexWrap:"wrap"}}>
      <button className="btn btn-sm btn-p" onClick={()=>setPerms(ALL_PERMISSIONS.map(p=>p.key))}>Select All</button>
      <button className="btn btn-sm" onClick={()=>setPerms([])}>Clear All</button>
      <button className="btn btn-sm" onClick={()=>setPerms([...(ROLE_DEFAULTS[user.role]||[])])}>Reset to Role Default</button>
    </div>
    {Object.entries(groups).map(([group,items])=><div key={group} style={{marginBottom:14}}>
      <div className="sec-title" style={{marginTop:0}}>{group}</div>
      <div className="perm-grid">{items.map(p=><div key={p.key} className={`perm-item ${perms.includes(p.key)?"on":""}`} onClick={()=>toggle(p.key)}>
        <div className="perm-check">{perms.includes(p.key)?"âœ“":""}</div>
        <span>{p.label}</span>
      </div>)}</div>
    </div>)}
    <div className="modal-actions"><button className="btn" onClick={onClose}>Cancel</button><button className="btn btn-p" onClick={save}>ðŸ’¾ Save Permissions</button></div>
  </div></div>;
}

