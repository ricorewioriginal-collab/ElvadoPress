namespace ElvadoPress.App.Windows;

public sealed class Station
{
    public string Id { get; set; } = "";
    public string Name { get; set; } = "";
    public string Description { get; set; } = "";
    public string Cover { get; set; } = "";
    public List<string> Genres { get; set; } = new();
    public string GenreText => Genres.Count == 0 ? "laut.fm" : string.Join(" · ", Genres.Take(3));
}

public sealed class ScheduleItem
{
    public string Time { get; set; } = "";
    public string Title { get; set; } = "";
    public string Description { get; set; } = "";
}

public sealed class ScheduleSlot
{
    public int Day { get; set; }          // 0 = Montag ... 6 = Sonntag
    public int Start { get; set; }
    public int End { get; set; }
    public string Title { get; set; } = "";
    public string Description { get; set; } = "";
    public string Time => $"{Start:00}:00 – {(End == 0 ? 24 : End):00}:00";
}

public sealed class PodcastEpisode
{
    public string Title { get; set; } = "";
    public string Date { get; set; } = "";
    public string AudioUrl { get; set; } = "";
    public string Image { get; set; } = "";
    public string Description { get; set; } = "";
}

public sealed class NewsArticle
{
    public int Id { get; set; }
    public string Slug { get; set; } = "";
    public string Title { get; set; } = "";
    public string Category { get; set; } = "News";
    public string Excerpt { get; set; } = "";
    public string ImageUrl { get; set; } = "";
    public string ImageMode { get; set; } = "thumbnail";
    public string ExternalUrl { get; set; } = "";
    public string VideoUrl { get; set; } = "";
    public string EmbedHtml { get; set; } = "";
    public string BodyHtml { get; set; } = "";
    public string Author { get; set; } = "";
    public string PublishedAt { get; set; } = "";
    public string CreatedAt { get; set; } = "";
}

public sealed class AppConfigData
{
    public List<string> OwnedStations { get; set; } = new();
    public Dictionary<string,string> Links { get; set; } = new();
    public string CommunityBase { get; set; } = "";
}
